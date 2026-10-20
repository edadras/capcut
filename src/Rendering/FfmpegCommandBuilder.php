<?php

namespace ReelMaker\Rendering;

use InvalidArgumentException;
use ReelMaker\Support\Catalog;

/**
 * Turns a RenderPlan into an FFmpeg argument list (no shell involved).
 *
 * Pipeline per photo:
 *   layout (fill / blurred bg) -> zoompan (Ken Burns + zoom punches + beat pulse + whip pans)
 *   -> rotate (spins) -> crop (shake) -> motion blur -> glitch -> color grade -> flashes
 * Then clips are joined with xfade (or concat when every boundary is a cut / effect cut),
 * overlays (light leaks, grain, letterbox) and animated captions are added, and the
 * music is trimmed / faded to the exact video length.
 *
 * "Effect" transitions are hard cuts on the beat whose look comes from both sides of the
 * cut: the outgoing clip accelerates into the effect, the incoming clip decelerates out of it.
 */
final class FfmpegCommandBuilder
{
    private const MIXED_MOTIONS = ['zoom_in', 'pan_right', 'zoom_out', 'pan_left', 'pan_up', 'pan_down'];

    /** Seconds used for a "cut" boundary when mixed with real transitions. */
    private const CUT_SECONDS = 0.04;

    /** Max length (s) of each side of an effect transition. */
    private const FX_WINDOW = [
        'zoom_in' => 0.3, 'zoom_spin' => 0.35, 'spin' => 0.35, 'blur' => 0.3,
        'whip_left' => 0.22, 'whip_right' => 0.22, 'whip_up' => 0.22, 'whip_down' => 0.22,
        'flash' => 0.25, 'glitch' => 0.2, 'shake' => 0.25,
    ];

    /** Spin angle (radians) reached at the cut. 0.6 rad needs ~1.85x oversize to hide corners. */
    private const SPIN_ANGLE = 0.6;

    public function __construct(private readonly string $ffmpeg = 'ffmpeg')
    {
    }

    /**
     * Work out per-clip timing.
     *
     * @return array{slide: float, beat: float, total: float, all_cut: bool, clips: list<float>,
     *     boundaries: list<array{type: string, duration: float, offset: float, fx: ?string}>}
     */
    public function timeline(RenderPlan $plan): array
    {
        $spec = $plan->spec;
        $n = count($plan->images);
        if ($n < 1) {
            throw new InvalidArgumentException('At least one image is required.');
        }

        $slide = $spec->slideDuration;

        if ($plan->maxDuration !== null && $n * $slide > $plan->maxDuration) {
            $slide = $plan->maxDuration / $n;
        }

        $beat = null;
        // Snap each photo to a whole number of beats so cuts land on the music.
        if ($spec->beatSync && $plan->bpm !== null && $plan->bpm > 0) {
            $beat = 60.0 / $plan->bpm;
            $beats = max(1, (int) round($slide / $beat));
            while ($plan->maxDuration !== null && $beats > 1 && $n * $beats * $beat > $plan->maxDuration) {
                $beats--;
            }
            $slide = $beats * $beat;
        }

        $slide = max(0.3, $slide);
        $allCut = array_diff($spec->transitions, ['cut', ...array_keys(Catalog::FX_TRANSITIONS)]) === [];

        // Each clip is extended by its outgoing transition so every photo is
        // fully visible for exactly $slide seconds and transitions start on the beat.
        $boundaries = [];
        $clips = [];
        for ($i = 0; $i < $n; $i++) {
            $td = 0.0;
            if ($i < $n - 1) {
                $type = $spec->transitions[($i + $spec->variant) % count($spec->transitions)];
                $fx = isset(Catalog::FX_TRANSITIONS[$type]) ? $type : null;
                $isCut = $type === 'cut' || $fx !== null;
                if (! $allCut) {
                    $td = $isCut ? self::CUT_SECONDS : min($spec->transitionDuration, $slide * 0.45);
                }
                $boundaries[$i] = ['type' => $isCut ? 'fade' : $type, 'duration' => round($td, 4), 'offset' => 0.0, 'fx' => $fx];
            }
            $clips[$i] = round($slide + $td, 4);
        }

        $merged = $clips[0];
        foreach ($boundaries as $k => $b) {
            $boundaries[$k]['offset'] = $allCut ? round(($k + 1) * $slide, 4) : round($merged - $b['duration'], 4);
            $merged += $clips[$k + 1] - $b['duration'];
        }

        return [
            'slide' => round($slide, 4),
            'beat' => round($beat ?? $slide, 4),
            'total' => round($n * $slide, 4),
            'clips' => $clips,
            'boundaries' => $boundaries,
            'all_cut' => $allCut,
        ];
    }

    /**
     * @return list<string>
     */
    public function build(RenderPlan $plan): array
    {
        $spec = $plan->spec;
        $t = $this->timeline($plan);
        $n = count($plan->images);
        $total = $t['total'];

        $args = [$this->ffmpeg, '-hide_banner', '-loglevel', 'error', '-y'];

        foreach ($plan->images as $image) {
            $args[] = '-i';
            $args[] = $image;
        }

        $hasAudio = $plan->audio !== null;
        if ($hasAudio) {
            if ($plan->audioStart > 0) {
                $args[] = '-ss';
                $args[] = $this->num($plan->audioStart);
            }
            $args[] = '-i';
            $args[] = $plan->audio;
        }

        $graph = [];
        foreach ($plan->images as $i => $_) {
            $edges = [];
            $in = $i === 0 ? $spec->intro : ($t['boundaries'][$i - 1]['fx'] ?? null);
            $out = $t['boundaries'][$i]['fx'] ?? null;
            if ($in !== null) {
                $edges[] = ['fx' => $in, 'side' => 'in'];
            }
            if ($out !== null) {
                $edges[] = ['fx' => $out, 'side' => 'out'];
            }
            $graph[] = $this->clipChain($plan, $i, $t, $edges);
        }

        if ($n === 1) {
            $graph[] = '[c0]null[joined]';
        } elseif ($t['all_cut']) {
            $labels = implode('', array_map(fn ($i) => "[c$i]", range(0, $n - 1)));
            $graph[] = "{$labels}concat=n=$n:v=1:a=0[joined]";
        } else {
            $prev = 'c0';
            foreach ($t['boundaries'] as $k => $b) {
                $out = $k === $n - 2 ? 'joined' : "x$k";
                $graph[] = sprintf(
                    '[%s][c%d]xfade=transition=%s:duration=%s:offset=%s[%s]',
                    $prev, $k + 1, $b['type'], $this->num($b['duration']), $this->num($b['offset']), $out
                );
                $prev = $out;
            }
        }

        $video = 'joined';
        if ($spec->overlay === 'light_leak') {
            $graph[] = $this->lightLeak($plan, $total, $video, 'leaked');
            $video = 'leaked';
        }

        $post = $this->overlayChain($plan, $total);
        $post[] = $this->textChain($plan, $total);
        if ($spec->fadeOut && $total > 2) {
            $post[] = 'fade=t=out:st='.$this->num($total - 0.4).':d=0.4';
        }
        $post[] = 'format=yuv420p';
        $graph[] = "[$video]".implode(',', array_filter($post)).'[vout]';

        if ($hasAudio) {
            $fadeOut = min(1.5, $total / 3);
            $graph[] = sprintf(
                '[%d:a]apad,atrim=0:%s,asetpts=PTS-STARTPTS,afade=t=in:d=0.05,afade=t=out:st=%s:d=%s[aout]',
                $n, $this->num($total), $this->num($total - $fadeOut), $this->num($fadeOut)
            );
        }

        array_push($args, '-filter_complex', implode(';', $graph), '-map', '[vout]');
        if ($hasAudio) {
            array_push($args, '-map', '[aout]', '-c:a', 'aac', '-b:a', '192k', '-ar', '44100');
        }

        array_push(
            $args,
            '-c:v', 'libx264',
            '-preset', $plan->preset,
            '-crf', (string) $plan->crf,
            '-pix_fmt', 'yuv420p',
            '-r', (string) $plan->fps,
            '-t', $this->num($total),
            '-movflags', '+faststart',
            $plan->output,
        );

        return $args;
    }

    /**
     * One photo -> one clip. The clip is cut into segments: the effect windows next to
     * each cut are rendered oversized (room for spins / shakes) and at 4x frame rate
     * (blended down = real motion blur); the calm middle part is rendered cheaply.
     *
     * @param  list<array{fx: string, side: string}>  $edges
     */
    private function clipChain(RenderPlan $plan, int $i, array $t, array $edges): string
    {
        $spec = $plan->spec;
        $W = $plan->width;
        $H = $plan->height;
        $fps = $plan->fps;
        $slide = $t['slide'];
        $length = $t['clips'][$i];
        $frames = max(1, (int) round($length * $fps));
        $length = $frames / $fps;

        $motion = $spec->motion === 'mixed'
            ? self::MIXED_MOTIONS[($i + $spec->variant) % count(self::MIXED_MOTIONS)]
            : $spec->motion;

        // Effect windows, snapped to whole frames.
        $windows = [];
        foreach ($edges as $edge) {
            $w = min(self::FX_WINDOW[$edge['fx']], $slide * 0.4);
            $wf = max(1, (int) round($w * $fps));
            if ($edge['side'] === 'in') {
                $windows[] = $edge + ['from' => 0, 'to' => $wf, 'w' => $wf / $fps];
            } else {
                $cut = (int) round($slide * $fps);
                $windows[] = $edge + ['from' => max(0, $cut - $wf), 'to' => $frames, 'w' => $wf / $fps];
            }
        }
        usort($windows, fn ($a, $b) => $a['from'] <=> $b['from']);

        // Expressions are written against a clip-local time placeholder {T}.
        $zoom = [];
        $xs = [];
        $ys = [];
        $angle = [];
        $bright = [];
        $sx = [];
        $sy = [];
        $post = [];

        foreach ($windows as $win) {
            $w = $this->num($win['w']);
            $p = $win['side'] === 'out'
                ? 'clip(({T}-'.$this->num($slide - $win['w']).")/$w,0,1)"
                : "clip(1-{T}/$w,0,1)";
            $dir = $win['side'] === 'out' ? 1 : -1;
            $range = 'between(t,'.$this->num($win['from'] / $fps).','.$this->num($win['to'] / $fps).')';

            switch ($win['fx']) {
                case 'zoom_in':
                    $zoom[] = "(1+0.9*pow($p,2))";
                    break;
                case 'zoom_spin':
                    $zoom[] = "(1+0.6*pow($p,2))";
                    $angle[] = ($dir * self::SPIN_ANGLE)."*pow($p,2)";
                    break;
                case 'spin':
                    $zoom[] = "(1+0.2*pow($p,2))";
                    $angle[] = ($dir * self::SPIN_ANGLE)."*pow($p,2)";
                    break;
                case 'blur':
                    $zoom[] = "(1+0.15*$p)";
                    $bright[] = "0.12*pow($p,3)";
                    $post[] = "gblur=sigma=18:enable='$range*gt($p,0.35)'";
                    break;
                case 'whip_left':
                case 'whip_right':
                case 'whip_up':
                case 'whip_down':
                    $zoom[] = "(1+0.4*$p)";
                    $horizontal = in_array($win['fx'], ['whip_left', 'whip_right'], true);
                    $sign = in_array($win['fx'], ['whip_left', 'whip_up'], true) ? $dir : -$dir;
                    $horizontal ? $xs[] = "$sign*pow($p,2)" : $ys[] = "$sign*pow($p,2)";
                    $post[] = 'dblur=angle='.($horizontal ? 0 : 90).':radius=90:enable=\''.$range.'*gt('.$p.',0.15)\'';
                    break;
                case 'flash':
                    $zoom[] = "(1+0.08*$p)";
                    $bright[] = "0.9*pow($p,3)";
                    break;
                case 'glitch':
                    $sx[] = "0.035*iw*sin({T}*97)*$p";
                    $sy[] = "0.012*ih*sin({T}*61)*$p";
                    $post[] = "rgbashift=rh=-28:bh=28:gv=10:edge=smear:enable='$range*gt($p,0.2)'";
                    $post[] = "noise=alls=20:allf=t:enable='$range*gt($p,0.2)'";
                    break;
                case 'shake':
                    $zoom[] = "(1+0.06*$p)";
                    $sx[] = "0.04*ow*sin({T}*71)*$p";
                    $sy[] = "0.03*oh*cos({T}*53)*$p";
                    break;
            }
        }

        // Beat effects: a decaying kick at the start of every beat.
        $b = $this->num($t['beat']);
        if ($spec->beatEffect === 'pulse') {
            $zoom[] = "(1+0.07*exp(-14*mod({T},$b)))";
        } elseif ($spec->beatEffect === 'flash') {
            $bright[] = "0.12*exp(-16*mod({T},$b))";
        } elseif ($spec->beatEffect === 'shake') {
            $sx[] = "0.012*ow*sin({T}*83)*exp(-10*mod({T},$b))";
            $sy[] = "0.008*oh*cos({T}*67)*exp(-10*mod({T},$b))";
        }

        [$z, $x, $y] = $this->zoompanExpr($motion, $this->num($length));
        if ($zoom !== []) {
            $z = "($z)*".implode('*', $zoom);
        }
        if ($xs !== []) {
            $x = "$x+(".implode('+', $xs).')*(iw-iw/zoom)/2';
        }
        if ($ys !== []) {
            $y = "$y+(".implode('+', $ys).')*(ih-ih/zoom)/2';
        }
        $rot = implode('+', $angle);
        $shakeX = implode('+', $sx);
        $shakeY = implode('+', $sy);

        // Split the clip into segments at window borders.
        $cuts = [0, $frames];
        foreach ($windows as $win) {
            array_push($cuts, $win['from'], $win['to']);
        }
        $cuts = array_values(array_unique($cuts));
        sort($cuts);

        $segments = [];
        $maxOver = 1.0;
        for ($k = 0; $k < count($cuts) - 1; $k++) {
            [$from, $to] = [$cuts[$k], $cuts[$k + 1]];
            $active = array_values(array_filter($windows, fn ($w) => $w['from'] < $to && $w['to'] > $from));
            $fx = array_column($active, 'fx');

            $spins = array_intersect($fx, ['spin', 'zoom_spin']) !== [];
            $shaky = array_intersect($fx, ['shake', 'glitch']) !== [] || $spec->beatEffect === 'shake';
            $over = $spins ? 1.85 : ($shaky ? 1.1 : 1.0);
            $ss = array_intersect($fx, ['zoom_in', 'zoom_spin', 'spin', 'shake', 'blur']) !== [] ? 4 : 1;

            $segments[] = compact('from', 'to', 'over', 'ss', 'spins');
            $maxOver = max($maxOver, $over);
        }

        $scale = max($plan->prescale, $maxOver);
        $pw = $this->even($W * $scale);
        $ph = $this->even($H * $scale);

        $chain = [];
        if ($spec->layout === 'blur') {
            $chain[] = "[$i:v]format=yuv420p,split[bg$i][fg$i]";
            $chain[] = "[bg$i]scale=$pw:$ph:force_original_aspect_ratio=increase,crop=$pw:$ph,gblur=sigma=40,eq=brightness=-0.06[bgb$i]";
            $chain[] = "[fg$i]scale=$pw:$ph:force_original_aspect_ratio=decrease:force_divisible_by=2[fgs$i]";
            $chain[] = "[bgb$i][fgs$i]overlay=(W-w)/2:(H-h)/2,setsar=1[lay$i]";
        } else {
            $chain[] = "[$i:v]format=yuv420p,scale=$pw:$ph:force_original_aspect_ratio=increase,crop=$pw:$ph,setsar=1[lay$i]";
        }

        $n = count($segments);
        $layLabels = array_map(fn ($k) => "[lay{$i}_$k]", range(0, $n - 1));
        $chain[] = $n === 1 ? "[lay$i]null{$layLabels[0]}" : "[lay$i]split=$n".implode('', $layLabels);

        $segLabels = [];
        foreach ($segments as $k => $seg) {
            $sfps = $fps * $seg['ss'];
            $start = $this->num($seg['from'] / $fps);
            $d = ($seg['to'] - $seg['from']) * $seg['ss'];
            // Motion-blurred segments don't need full detail: render them at half size.
            $r = $seg['ss'] > 1 ? 0.5 : 1.0;
            [$cw, $ch] = [$this->even($W * $r), $this->even($H * $r)];
            $ow = $this->even($cw * $seg['over']);
            $oh = $this->even($ch * $seg['over']);
            $tz = "($start+on/$sfps)";
            $tt = "($start+t)";

            $f = ["zoompan=z='".str_replace('{T}', $tz, $z)."':x='".str_replace('{T}', $tz, $x)."':y='".str_replace('{T}', $tz, $y)."':d=$d:s={$ow}x{$oh}:fps=$sfps"];
            if ($seg['spins'] && $rot !== '') {
                $f[] = "rotate=a='".str_replace('{T}', $tt, $rot)."':c=black";
            }
            if ($seg['over'] > 1.0) {
                $cx = '(iw-ow)/2'.($shakeX !== '' ? '+'.str_replace('{T}', $tt, $shakeX) : '');
                $cy = '(ih-oh)/2'.($shakeY !== '' ? '+'.str_replace('{T}', $tt, $shakeY) : '');
                $f[] = "crop=$cw:$ch:x='$cx':y='$cy'";
            }
            if ($seg['ss'] > 1) {
                // Average each group of sub-frames into one output frame.
                $f[] = "tmix=frames={$seg['ss']},select='eq(mod(n,{$seg['ss']}),".($seg['ss'] - 1).")'";
            }
            if ($r < 1.0) {
                $f[] = "scale=$W:$H:flags=bicubic";
            }
            $f[] = 'setsar=1,format=yuv420p';

            $label = "[s{$i}_$k]";
            $chain[] = $layLabels[$k].implode(',', $f).$label;
            $segLabels[] = $label;
        }

        $finish = $n === 1 ? ["null"] : ["concat=n=$n:v=1:a=0"];
        $finish[] = 'settb=AVTB,setpts=N/'.$fps.'/TB,fps='.$fps;
        array_push($finish, ...array_map(fn ($p) => str_replace('{T}', 't', $p), $post));
        if ($grade = Catalog::filterChain($spec->filter)) {
            $finish[] = $grade;
        }
        if ($bright !== []) {
            // Wash towards white: lift luma and drain chroma together (avoids colour casts).
            $flash = 'min(1,'.str_replace('{T}', 't', implode('+', $bright)).')';
            $finish[] = "eq=brightness='$flash':saturation='max(0,1-$flash)':eval=frame";
        }
        $finish[] = 'setsar=1';
        $finish[] = 'format=yuv420p';

        $chain[] = implode('', $segLabels).implode(',', $finish)."[c$i]";

        return implode(';', $chain);
    }

    /**
     * Ken Burns base motion as a function of clip-local time {T}.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function zoompanExpr(string $motion, string $length): array
    {
        $p = "min(1,{T}/$length)";
        $cx = 'iw/2-(iw/zoom/2)';
        $cy = 'ih/2-(ih/zoom/2)';
        $zmax = '1.18';

        return match ($motion) {
            'zoom_in' => ["1+0.18*$p", $cx, $cy],
            'zoom_out' => ["$zmax-0.18*$p", $cx, $cy],
            'pan_left' => [$zmax, "(iw-iw/zoom)*(1-$p)", $cy],
            'pan_right' => [$zmax, "(iw-iw/zoom)*$p", $cy],
            'pan_up' => [$zmax, $cx, "(ih-ih/zoom)*(1-$p)"],
            'pan_down' => [$zmax, $cx, "(ih-ih/zoom)*$p"],
            default => ['1', $cx, $cy],
        };
    }

    private function lightLeak(RenderPlan $plan, float $total, string $in, string $out): string
    {
        // A soft, slowly drifting warm glow from one corner, alpha-blended over the video.
        $w = $this->even($plan->width / 8);
        $h = $this->even($plan->height / 8);
        $seed = $plan->spec->variant + 1;
        [$x0, $y0] = [[0, 0], [$w, 0], [0, $h], [$w, $h]][$plan->spec->variant % 4];

        return "gradients=s={$w}x{$h}:r={$plan->fps}:d=".$this->num($total)
            .":c0=0xffa04a78:c1=0xff3c7850:c2=0xffd27a00:c3=0x00000000:nb_colors=4:type=radial"
            .":x0=$x0:y0=$y0:x1=".($w - $x0).':y1='.($h - $y0).":speed=0.012:seed=$seed,"
            ."gblur=sigma=4,scale={$plan->width}:{$plan->height}[leak];"
            ."[$in][leak]overlay=format=auto,format=yuv420p[$out]";
    }

    /**
     * @return list<string>
     */
    private function overlayChain(RenderPlan $plan, float $total): array
    {
        return match ($plan->spec->overlay) {
            'grain' => ['noise=alls=10:allf=t+u'],
            'letterbox' => ['drawbox=x=0:y=0:w=iw:h=ih*0.09:color=black:t=fill', 'drawbox=x=0:y=ih*0.91:w=iw:h=ih*0.09:color=black:t=fill'],
            'film' => ['noise=alls=14:allf=t+u', "eq=brightness='0.025*sin(t*37)':eval=frame", 'vignette=PI/4'],
            default => [],
        };
    }

    private function textChain(RenderPlan $plan, float $total): string
    {
        $style = $plan->spec->textStyle;
        if ($style === 'none' || $plan->textFile === null || $plan->fontFile === null) {
            return '';
        }

        $size = (int) round($plan->width * 0.075);
        [$x, $y] = match ($style) {
            'top' => ['(w-text_w)/2', 'h*0.12'],
            'bottom', 'box' => ['(w-text_w)/2', 'h*0.80-text_h'],
            default => ['(w-text_w)/2', '(h-text_h)/2'],
        };

        // Text appears at 0.3s and fades out with the video.
        $end = $this->num($total - 0.4);
        $fadeOut = "if(gt(t,$end),max(0,1-(t-$end)/0.4),1)";
        $fontsize = (string) $size;

        switch ($plan->spec->textAnimation) {
            case 'pop':
                // easeOutBack scale-in over 0.35s
                $p = 'min(1,max(0,(t-0.3)/0.35))';
                $fontsize = "max(1,$size*(1+2.70158*pow($p-1,3)+1.70158*pow($p-1,2)))";
                $alpha = "if(lt(t,0.3),0,$fadeOut)";
                break;
            case 'slide_up':
                $p = 'min(1,max(0,(t-0.3)/0.5))';
                $y = "$y+".(int) round($plan->height * 0.05)."*pow(1-$p,3)";
                $alpha = "$p*$fadeOut";
                break;
            default:
                $alpha = "min(1,max(0,(t-0.3)/0.6))*$fadeOut";
        }

        $opts = [
            'fontfile='.$this->quote($plan->fontFile),
            'textfile='.$this->quote($plan->textFile),
            "fontsize='$fontsize'",
            'fontcolor=white',
            'line_spacing=18',
            "x=$x",
            "y='$y'",
            "alpha='$alpha'",
        ];

        if ($plan->centerText) {
            $opts[] = 'text_align=center'; // FFmpeg >= 6.1
        }

        if ($style === 'box') {
            array_push($opts, 'box=1', 'boxcolor=black@0.45', 'boxborderw=36');
        } else {
            array_push($opts, 'shadowcolor=black@0.6', 'shadowx=3', 'shadowy=3', 'borderw=2', 'bordercolor=black@0.35');
        }

        return 'drawtext='.implode(':', $opts);
    }

    private function quote(string $path): string
    {
        if (str_contains($path, "'")) {
            throw new InvalidArgumentException("Path may not contain quotes: $path");
        }

        return "'".str_replace('\\', '/', $path)."'";
    }

    private function even(float $v): int
    {
        return (int) (2 * round($v / 2));
    }

    private function num(float $v): string
    {
        return rtrim(rtrim(number_format($v, 4, '.', ''), '0'), '.') ?: '0';
    }
}

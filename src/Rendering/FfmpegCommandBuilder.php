<?php

namespace ReelMaker\Rendering;

use InvalidArgumentException;
use ReelMaker\Support\Catalog;

/**
 * Turns a RenderPlan into an FFmpeg argument list (no shell involved).
 *
 * Pipeline per photo:  layout (fill / blurred bg) -> Ken Burns (zoompan) -> color grade
 * Then all clips are joined with xfade (or concat for hard cuts), captions are drawn,
 * and the music is trimmed / faded to the exact video length.
 */
final class FfmpegCommandBuilder
{
    private const MIXED_MOTIONS = ['zoom_in', 'pan_right', 'zoom_out', 'pan_left', 'pan_up', 'pan_down'];

    /** Seconds used for a "cut" boundary when mixed with real transitions. */
    private const CUT_SECONDS = 0.04;

    public function __construct(private readonly string $ffmpeg = 'ffmpeg')
    {
    }

    /**
     * Work out per-clip timing.
     *
     * @return array{slide: float, total: float, clips: list<float>, boundaries: list<array{type: string, duration: float, offset: float}>}
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
        $allCut = array_unique($spec->transitions) === ['cut'];

        // Each clip is extended by its outgoing transition so every photo is
        // fully visible for exactly $slide seconds and transitions start on the beat.
        $boundaries = [];
        $clips = [];
        for ($i = 0; $i < $n; $i++) {
            $td = 0.0;
            if ($i < $n - 1 && ! $allCut) {
                $type = $spec->transitions[($i + $spec->variant) % count($spec->transitions)];
                $td = $type === 'cut' ? self::CUT_SECONDS : min($spec->transitionDuration, $slide * 0.45);
                $boundaries[$i] = ['type' => $type === 'cut' ? 'fade' : $type, 'duration' => round($td, 4), 'offset' => 0.0];
            }
            $clips[$i] = round($slide + $td, 4);
        }

        $merged = $clips[0];
        foreach ($boundaries as $k => $b) {
            $boundaries[$k]['offset'] = round($merged - $b['duration'], 4);
            $merged += $clips[$k + 1] - $b['duration'];
        }

        return [
            'slide' => round($slide, 4),
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
            $graph[] = $this->clipChain($plan, $i, $t['clips'][$i]);
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

        $graph[] = '[joined]'.$this->textChain($plan, $t['total']).'format=yuv420p[vout]';

        if ($hasAudio) {
            $total = $t['total'];
            $fadeOut = min(1.5, $total / 3);
            $graph[] = sprintf(
                '[%d:a]apad,atrim=0:%s,asetpts=PTS-STARTPTS,afade=t=in:d=0.15,afade=t=out:st=%s:d=%s[aout]',
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
            '-t', $this->num($t['total']),
            '-movflags', '+faststart',
            $plan->output,
        );

        return $args;
    }

    private function clipChain(RenderPlan $plan, int $i, float $duration): string
    {
        $spec = $plan->spec;
        $W = $plan->width;
        $H = $plan->height;
        $pw = $this->even($W * $plan->prescale);
        $ph = $this->even($H * $plan->prescale);
        $frames = max(1, (int) round($duration * $plan->fps));

        $chain = [];
        if ($spec->layout === 'blur') {
            $chain[] = "[$i:v]format=yuv420p,split[bg$i][fg$i]";
            $chain[] = "[bg$i]scale=$pw:$ph:force_original_aspect_ratio=increase,crop=$pw:$ph,gblur=sigma=40,eq=brightness=-0.06[bgb$i]";
            $chain[] = "[fg$i]scale=$pw:$ph:force_original_aspect_ratio=decrease:force_divisible_by=2[fgs$i]";
            $chain[] = "[bgb$i][fgs$i]overlay=(W-w)/2:(H-h)/2,setsar=1[lay$i]";
        } else {
            $chain[] = "[$i:v]format=yuv420p,scale=$pw:$ph:force_original_aspect_ratio=increase,crop=$pw:$ph,setsar=1[lay$i]";
        }

        $motion = $spec->motion === 'mixed'
            ? self::MIXED_MOTIONS[($i + $spec->variant) % count(self::MIXED_MOTIONS)]
            : $spec->motion;

        [$z, $x, $y] = $this->zoompanExpr($motion, $frames);
        $post = ["zoompan=z='$z':x='$x':y='$y':d=$frames:s={$W}x{$H}:fps={$plan->fps}"];

        if ($grade = Catalog::filterChain($spec->filter)) {
            $post[] = $grade;
        }
        $post[] = 'setsar=1';
        $post[] = 'format=yuv420p';

        $chain[] = "[lay$i]".implode(',', $post)."[c$i]";

        return implode(';', $chain);
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function zoompanExpr(string $motion, int $frames): array
    {
        $p = "on/$frames";
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
            default => ['1', '0', '0'],
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

        $opts = [
            'fontfile='.$this->quote($plan->fontFile),
            'textfile='.$this->quote($plan->textFile),
            "fontsize=$size",
            'fontcolor=white',
            'line_spacing=18',
            "x=$x",
            "y=$y",
            "alpha='if(lt(t,0.3),0,if(lt(t,0.9),(t-0.3)/0.6,if(gt(t,".$this->num($total - 0.6).'),max(0,('.$this->num($total).'-t)/0.6),1)))\'',
        ];

        if ($plan->centerText) {
            $opts[] = 'text_align=center'; // FFmpeg >= 6.1
        }

        if ($style === 'box') {
            array_push($opts, 'box=1', 'boxcolor=black@0.45', 'boxborderw=36');
        } else {
            array_push($opts, 'shadowcolor=black@0.6', 'shadowx=3', 'shadowy=3', 'borderw=2', 'bordercolor=black@0.35');
        }

        return 'drawtext='.implode(':', $opts).',';
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

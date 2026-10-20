<?php

namespace ReelMaker\Rendering;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Reads duration and estimates tempo (BPM) of a music file so templates can cut on the beat.
 */
final class AudioAnalyzer
{
    private const SAMPLE_RATE = 11025;

    private const HOP = 128;

    public function __construct(
        private readonly string $ffmpeg = 'ffmpeg',
        private readonly string $ffprobe = 'ffprobe',
    ) {
    }

    public function duration(string $path): float
    {
        $p = new Process([$this->ffprobe, '-v', 'error', '-show_entries', 'format=duration', '-of', 'default=nw=1:nk=1', $path]);
        $p->mustRun();

        return (float) trim($p->getOutput());
    }

    /**
     * Onset-envelope autocorrelation tempo estimate, limited to 70–180 BPM.
     */
    public function bpm(string $path, float $start = 0.0, float $length = 45.0): ?float
    {
        $p = new Process([
            $this->ffmpeg, '-v', 'error', '-ss', (string) $start, '-t', (string) $length, '-i', $path,
            '-ac', '1', '-ar', (string) self::SAMPLE_RATE, '-f', 's16le', '-',
        ]);
        $p->setTimeout(120);
        $p->run();
        if (! $p->isSuccessful()) {
            throw new RuntimeException('Could not decode audio: '.$p->getErrorOutput());
        }

        $pcm = $p->getOutput();
        $samples = unpack('s*', $pcm) ?: [];

        return $this->bpmFromSamples(array_values($samples));
    }

    /**
     * @param  list<int>  $samples  mono 16-bit samples at SAMPLE_RATE
     */
    public function bpmFromSamples(array $samples): ?float
    {
        $frames = intdiv(count($samples), self::HOP);
        if ($frames < 200) {
            return null;
        }

        // Energy envelope -> positive flux (onset strength).
        $prev = 0.0;
        $onset = [];
        for ($f = 0; $f < $frames; $f++) {
            $e = 0.0;
            $base = $f * self::HOP;
            for ($i = 0; $i < self::HOP; $i++) {
                $s = $samples[$base + $i];
                $e += $s * $s;
            }
            $e = log(1 + $e / self::HOP);
            $onset[] = max(0.0, $e - $prev);
            $prev = $e;
        }

        $mean = array_sum($onset) / count($onset);
        $onset = array_map(fn ($v) => $v - $mean, $onset);

        $fps = self::SAMPLE_RATE / self::HOP;
        $minLag = (int) floor($fps * 60 / 180);
        $maxLag = (int) ceil($fps * 60 / 70);

        $count = count($onset);
        $acf = [];
        for ($lag = $minLag - 1; $lag <= $maxLag + 1; $lag++) {
            $sum = 0.0;
            for ($i = $lag; $i < $count; $i++) {
                $sum += $onset[$i] * $onset[$i - $lag];
            }
            $acf[$lag] = $sum;
        }

        $best = null;
        $bestScore = 0.0;
        for ($lag = $minLag; $lag <= $maxLag; $lag++) {
            // Mild preference for tempos around 120 BPM.
            $score = $acf[$lag] * exp(-0.5 * log(60 * $fps / $lag / 120, 2) ** 2);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $lag;
            }
        }

        if ($best === null) {
            return null;
        }

        // Parabolic interpolation for sub-frame lag precision.
        [$a, $b, $c] = [$acf[$best - 1], $acf[$best], $acf[$best + 1]];
        $den = $a - 2 * $b + $c;
        $lag = $best + ($den != 0.0 ? 0.5 * ($a - $c) / $den : 0.0);

        return round(60 * $fps / $lag, 1);
    }
}

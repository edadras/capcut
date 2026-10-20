<?php

namespace ReelMaker\Rendering;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use ReelMaker\Models\Render;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Renders a queued Render record to an MP4 on the output disk.
 */
class Renderer
{
    public function __construct(private readonly FfmpegCommandBuilder $builder)
    {
    }

    public function render(Render $render): void
    {
        $config = config('reel-maker');
        $template = $render->template;
        $track = $render->track ?? $template->track;
        $spec = $template->spec();

        $uploads = Storage::disk($config['upload_disk']);
        $images = array_map(fn (string $p) => $uploads->path($p), $render->images);

        $work = storage_path('app/reel-maker-tmp/'.$render->uuid);
        File::ensureDirectoryExists($work);

        try {
            $textFile = null;
            if (filled($render->caption) && $spec->textStyle !== 'none') {
                $textFile = $work.'/caption.txt';
                file_put_contents($textFile, trim($render->caption));
            }

            $font = $config['font'];
            $plan = new RenderPlan(
                spec: $spec,
                images: $images,
                output: $work.'/out.mp4',
                audio: $track?->absolutePath(),
                audioStart: $track?->highlight_start ?? 0.0,
                bpm: $track?->bpm,
                textFile: $textFile,
                fontFile: is_file($font) ? $font : null,
                width: $config['width'],
                height: $config['height'],
                fps: $config['fps'],
                prescale: $config['zoompan_prescale'],
                crf: $config['crf'],
                preset: $config['preset'],
                maxDuration: $config['max_duration'][$spec->format] ?? null,
                centerText: $config['center_text'],
            );

            $process = new Process($this->builder->build($plan));
            $process->setTimeout($config['timeout']);
            $process->run();

            if (! $process->isSuccessful() || ! is_file($plan->output)) {
                throw new RuntimeException('FFmpeg failed: '.trim($process->getErrorOutput()));
            }

            $path = $config['directory'].'/outputs/'.$render->uuid.'.mp4';
            $stream = fopen($plan->output, 'rb');
            Storage::disk($config['output_disk'])->writeStream($path, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }

            $render->forceFill([
                'output_path' => $path,
                'duration' => $this->builder->timeline($plan)['total'],
            ])->save();
        } finally {
            File::deleteDirectory($work);
        }
    }
}

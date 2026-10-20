<?php

namespace ReelMaker\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use ReelMaker\Models\Template;
use ReelMaker\Rendering\FfmpegCommandBuilder;
use ReelMaker\Rendering\RenderPlan;
use Symfony\Component\Process\Process;
use Throwable;

class GeneratePreviewsCommand extends Command
{
    protected $signature = 'reel-maker:previews
        {--images= : Folder with sample photos (defaults to generated placeholders)}
        {--limit=100 : Max previews to build in this run}
        {--force : Rebuild previews that already exist}';

    protected $description = 'Render small preview videos for the template gallery';

    public function handle(FfmpegCommandBuilder $builder): int
    {
        $work = storage_path('app/reel-maker-tmp/previews');
        File::ensureDirectoryExists($work);
        $samples = $this->samples($work);
        if ($samples === []) {
            $this->error('No sample images available.');

            return self::FAILURE;
        }

        $disk = Storage::disk(config('reel-maker.output_disk'));
        $font = config('reel-maker.font');
        $caption = $work.'/caption.txt';
        file_put_contents($caption, 'متن شما اینجا');

        $query = Template::active()->with('track')->orderByDesc('is_featured')->orderBy('id');
        if (! $this->option('force')) {
            $query->whereNull('preview_path');
        }

        $bar = $this->output->createProgressBar(min((int) $this->option('limit'), $query->count()));
        foreach ($query->limit((int) $this->option('limit'))->cursor() as $template) {
            $spec = $template->spec();
            $n = max($spec->minImages, min(5, $spec->maxImages));
            $images = array_map(fn ($i) => $samples[$i % count($samples)], range(0, $n - 1));
            $out = $work.'/'.$template->slug.'.mp4';

            $plan = new RenderPlan(
                spec: $spec,
                images: $images,
                output: $out,
                audio: $template->track?->absolutePath(),
                audioStart: $template->track?->highlight_start ?? 0.0,
                bpm: $template->track?->bpm,
                textFile: $caption,
                fontFile: is_file($font) ? $font : null,
                width: 360,
                height: 640,
                fps: 24,
                prescale: 1.0,
                crf: 30,
                preset: 'ultrafast',
                maxDuration: 12,
                centerText: config('reel-maker.center_text'),
            );

            try {
                (new Process($builder->build($plan)))->setTimeout(300)->mustRun();
                $path = config('reel-maker.directory').'/previews/'.$template->slug.'.mp4';
                $disk->put($path, file_get_contents($out));
                $template->forceFill(['preview_path' => $path])->save();
            } catch (Throwable $e) {
                $this->newLine();
                $this->warn("{$template->slug}: ".$e->getMessage());
            } finally {
                @unlink($out);
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function samples(string $work): array
    {
        if ($dir = $this->option('images')) {
            return array_values(glob(rtrim($dir, '/').'/*.{jpg,jpeg,png,webp}', GLOB_BRACE) ?: []);
        }

        $palette = [['#ff6b6b', '#feca57'], ['#48dbfb', '#5f27cd'], ['#1dd1a1', '#10ac84'], ['#ff9ff3', '#f368e0'], ['#54a0ff', '#2e86de']];
        $files = [];
        foreach ($palette as $i => [$a, $b]) {
            $file = "$work/sample-$i.jpg";
            if (! is_file($file)) {
                (new Process([
                    config('reel-maker.ffmpeg'), '-v', 'error', '-y', '-f', 'lavfi',
                    '-i', "gradients=s=720x1280:c0=$a:c1=$b:x0=0:y0=0:x1=720:y1=1280:seed=$i",
                    '-frames:v', '1', $file,
                ]))->run();
            }
            if (is_file($file)) {
                $files[] = $file;
            }
        }

        return $files;
    }
}

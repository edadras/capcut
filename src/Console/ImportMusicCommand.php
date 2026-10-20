<?php

namespace ReelMaker\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ReelMaker\Models\Track;
use ReelMaker\Rendering\AudioAnalyzer;
use ReelMaker\Support\Catalog;
use Symfony\Component\Finder\Finder;
use Throwable;

class ImportMusicCommand extends Command
{
    protected $signature = 'reel-maker:import-music
        {directory : Folder of audio files. Sub-folders named after a mood (happy, romantic, ...) set the mood}
        {--mood= : Mood for files that are not inside a mood folder}
        {--license= : License note stored with every track (e.g. "Pixabay Content License")}';

    protected $description = 'Import licensed / royalty-free music, detecting duration and BPM';

    public function handle(AudioAnalyzer $analyzer): int
    {
        $dir = rtrim($this->argument('directory'), '/');
        if (! is_dir($dir)) {
            $this->error("Directory not found: $dir");

            return self::FAILURE;
        }

        $disk = Storage::disk(config('reel-maker.music_disk'));
        $files = Finder::create()->files()->in($dir)->name(['*.mp3', '*.m4a', '*.aac', '*.wav', '*.ogg', '*.flac']);
        $imported = 0;

        foreach ($files as $file) {
            $folder = Str::lower(basename($file->getPath()));
            $mood = isset(Catalog::MOODS[$folder]) ? $folder : $this->option('mood');
            if (! isset(Catalog::MOODS[$mood ?? ''])) {
                $this->warn("Skipped {$file->getFilename()}: unknown mood (use a mood folder or --mood). Moods: ".implode(', ', array_keys(Catalog::MOODS)));

                continue;
            }

            try {
                $duration = $analyzer->duration($file->getRealPath());
                $bpm = $analyzer->bpm($file->getRealPath(), min(20.0, $duration / 4));
            } catch (Throwable $e) {
                $this->warn("Skipped {$file->getFilename()}: ".$e->getMessage());

                continue;
            }

            $name = pathinfo($file->getFilename(), PATHINFO_FILENAME);
            [$artist, $title] = str_contains($name, ' - ') ? explode(' - ', $name, 2) : [null, $name];
            $path = config('reel-maker.directory')."/music/$mood/".Str::slug($name).'-'.substr(md5_file($file->getRealPath()), 0, 8).'.'.$file->getExtension();

            $stream = fopen($file->getRealPath(), 'rb');
            $disk->writeStream($path, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }

            Track::updateOrCreate(['path' => $path], [
                'title' => trim($title),
                'artist' => $artist ? trim($artist) : null,
                'mood' => $mood,
                'duration' => $duration,
                'bpm' => $bpm,
                'license' => $this->option('license'),
            ]);

            $imported++;
            $this->line(sprintf('  ✓ %-40s %-10s %5.1fs  %s BPM', Str::limit($name, 38), $mood, $duration, $bpm ?? '?'));
        }

        $this->info("Imported $imported track(s).");

        return self::SUCCESS;
    }
}

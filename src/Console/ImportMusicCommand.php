<?php

namespace ReelMaker\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use ReelMaker\Support\Catalog;
use ReelMaker\Support\TrackImporter;
use Symfony\Component\Finder\Finder;
use Throwable;

class ImportMusicCommand extends Command
{
    protected $signature = 'reel-maker:import-music
        {directory : Folder of audio files. Sub-folders named after a mood (happy, romantic, ...) set the mood}
        {--mood= : Mood for files that are not inside a mood folder}
        {--license= : License note stored with every track (e.g. "Pixabay Content License")}';

    protected $description = 'Import licensed / royalty-free music, detecting duration and BPM';

    public function handle(TrackImporter $importer): int
    {
        $dir = rtrim($this->argument('directory'), '/');
        if (! is_dir($dir)) {
            $this->error("Directory not found: $dir");

            return self::FAILURE;
        }

        $files = Finder::create()->files()->in($dir)->name(['*.mp3', '*.m4a', '*.aac', '*.wav', '*.ogg', '*.flac']);
        $imported = 0;

        foreach ($files as $file) {
            $folder = Str::lower(basename($file->getPath()));
            $mood = isset(Catalog::MOODS[$folder]) ? $folder : $this->option('mood');
            if (! isset(Catalog::MOODS[$mood ?? ''])) {
                $this->warn("Skipped {$file->getFilename()}: unknown mood (use a mood folder or --mood). Moods: ".implode(', ', array_keys(Catalog::MOODS)));

                continue;
            }

            $name = pathinfo($file->getFilename(), PATHINFO_FILENAME);
            [$artist, $title] = str_contains($name, ' - ') ? explode(' - ', $name, 2) : [null, $name];

            try {
                $track = $importer->import($file->getRealPath(), $mood, [
                    'title' => $title,
                    'artist' => $artist,
                    'license' => $this->option('license'),
                ]);
            } catch (Throwable $e) {
                $this->warn("Skipped {$file->getFilename()}: ".$e->getMessage());

                continue;
            }

            $imported++;
            $this->line(sprintf('  ✓ %-40s %-10s %5.1fs  %s BPM', Str::limit($name, 38), $mood, $track->duration, $track->bpm ?? '?'));
        }

        $this->info("Imported $imported track(s).");

        return self::SUCCESS;
    }
}

<?php

namespace ReelMaker\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use ReelMaker\Models\Track;
use ReelMaker\Support\Catalog;

class ImportTrendingCommand extends Command
{
    protected $signature = 'reel-maker:import-trending
        {file? : JSON list of sounds (defaults to the bundled list)}
        {--keep-old : Keep Instagram sounds that are no longer in the list active}';

    protected $description = 'Import trending Instagram sounds (no audio: users add them inside Instagram when posting)';

    public function handle(): int
    {
        $file = $this->argument('file') ?? __DIR__.'/../../resources/data/instagram-trending.json';
        $data = json_decode((string) @file_get_contents($file), true);
        if (! is_array($data['sounds'] ?? null)) {
            $this->error("Could not read sounds from $file");

            return self::FAILURE;
        }

        $ids = [];
        foreach ($data['sounds'] as $s) {
            if (empty($s['title'])) {
                continue;
            }
            $mood = isset(Catalog::MOODS[$s['mood'] ?? '']) ? $s['mood'] : 'pop';
            $id = 'instagram:'.Str::slug($s['title'].' '.($s['artist'] ?? ''));
            $ids[] = $id;

            Track::updateOrCreate(['external_id' => $id], [
                'kind' => Track::INSTAGRAM,
                'title' => $s['title'],
                'artist' => $s['artist'] ?? null,
                'mood' => $mood,
                'region' => $s['region'] ?? null,
                'path' => null,
                'bpm' => $s['bpm'] ?? null,
                'highlight_start' => (float) ($s['start'] ?? 0),
                'links' => $s['links'] ?? [],
                'trending_at' => $s['trending_at'] ?? null,
                'license' => null,
                'is_active' => true,
            ]);
        }

        if (! $this->option('keep-old')) {
            $off = Track::instagram()->whereNotIn('external_id', $ids)->update(['is_active' => false]);
            if ($off) {
                $this->line("Deactivated $off sound(s) no longer trending.");
            }
        }

        $this->info('Imported '.count($ids).' Instagram sound(s). Add "bpm" in the JSON to make photo cuts land on the beat.');

        return self::SUCCESS;
    }
}

<?php

namespace ReelMaker\Console;

use Illuminate\Console\Command;
use ReelMaker\Models\Template;
use ReelMaker\Models\Track;
use ReelMaker\Support\Catalog;
use ReelMaker\Support\TemplateGenerator;

class GenerateTemplatesCommand extends Command
{
    protected $signature = 'reel-maker:generate-templates
        {--count=2000 : Number of templates to generate}
        {--category=* : Limit to these categories}
        {--seed=1 : Seed (same seed = same catalog, safe to re-run)}
        {--fresh : Delete existing templates that have never been used first}';

    protected $description = 'Generate a large catalog of Reels/Story templates matched to the music library';

    public function handle(TemplateGenerator $generator): int
    {
        $categories = $this->option('category') ?: null;
        foreach ($categories ?? [] as $c) {
            if (! isset(Catalog::CATEGORIES[$c])) {
                $this->error("Unknown category [$c]. Available: ".implode(', ', array_keys(Catalog::CATEGORIES)));

                return self::FAILURE;
            }
        }

        $tracks = Track::active()->files()->get(['id', 'mood'])
            ->groupBy('mood')
            ->map(fn ($g) => $g->map(fn ($t) => ['id' => $t->id, 'mood' => $t->mood])->values()->all())
            ->all();

        if ($tracks === []) {
            $this->warn('No music imported yet; templates will be silent until tracks are attached. Run reel-maker:import-music first.');
        }

        if ($this->option('fresh')) {
            Template::where('uses', 0)->delete();
        }

        $count = (int) $this->option('count');
        $bar = $this->output->createProgressBar($count);
        $batch = [];
        $now = now();

        foreach ($generator->generate($count, $tracks, $categories, (int) $this->option('seed')) as $row) {
            $row['config'] = json_encode($row['config']);
            $row['created_at'] = $row['updated_at'] = $now;
            $batch[] = $row;
            if (count($batch) === 500) {
                $this->flush($batch);
                $bar->advance(500);
                $batch = [];
            }
        }
        $this->flush($batch);
        $bar->finish();
        $this->newLine();
        $this->info('Templates in catalog: '.Template::count());

        return self::SUCCESS;
    }

    private function flush(array $batch): void
    {
        if ($batch !== []) {
            Template::upsert($batch, ['slug'], ['name', 'category', 'format', 'track_id', 'config', 'min_images', 'max_images', 'updated_at']);
        }
    }
}

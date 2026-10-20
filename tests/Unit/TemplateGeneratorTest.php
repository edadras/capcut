<?php

namespace ReelMaker\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReelMaker\Rendering\TemplateSpec;
use ReelMaker\Support\TemplateGenerator;

class TemplateGeneratorTest extends TestCase
{
    public function test_generates_unique_valid_deterministic_templates(): void
    {
        $tracks = ['happy' => [['id' => 1, 'mood' => 'happy']], 'calm' => [['id' => 2, 'mood' => 'calm']]];
        $a = iterator_to_array((new TemplateGenerator)->generate(3000, $tracks, null, 7), false);
        $b = iterator_to_array((new TemplateGenerator)->generate(3000, $tracks, null, 7), false);

        $this->assertCount(3000, $a);
        $this->assertSame(array_column($a, 'slug'), array_column($b, 'slug'));
        $this->assertCount(3000, array_unique(array_column($a, 'slug')));

        $fx = array_filter($a, fn ($r) => array_intersect($r['config']['transitions'], ['zoom_in', 'whip_left', 'spin', 'glitch']) !== []);
        $this->assertGreaterThan(300, count($fx), 'effect transitions should be common');
        $trend = array_filter($a, fn ($r) => $r['category'] === 'trend');
        $love = array_filter($a, fn ($r) => $r['category'] === 'love');
        $this->assertGreaterThan(
            count(array_filter($love, fn ($r) => $r['config']['beat_effect'] !== 'none')) / count($love),
            count(array_filter($trend, fn ($r) => $r['config']['beat_effect'] !== 'none')) / count($trend),
        );

        foreach ($a as $row) {
            TemplateSpec::fromArray($row['config']); // throws if invalid
            $this->assertContains($row['track_id'], [1, 2]);
        }
    }
}

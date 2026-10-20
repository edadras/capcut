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

        foreach ($a as $row) {
            TemplateSpec::fromArray($row['config']); // throws if invalid
            $this->assertContains($row['track_id'], [1, 2]);
        }
    }
}

<?php

namespace ReelMaker\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReelMaker\Rendering\FfmpegCommandBuilder;
use ReelMaker\Rendering\RenderPlan;
use ReelMaker\Rendering\TemplateSpec;

class FfmpegCommandBuilderTest extends TestCase
{
    private function plan(array $config, int $images = 4, ?float $bpm = null, ?float $max = null): RenderPlan
    {
        return new RenderPlan(
            spec: TemplateSpec::fromArray($config),
            images: array_map(fn ($i) => "/tmp/img$i.jpg", range(1, $images)),
            output: '/tmp/out.mp4',
            audio: '/tmp/song.mp3',
            bpm: $bpm,
            maxDuration: $max,
        );
    }

    public function test_transitions_start_exactly_on_slide_boundaries(): void
    {
        $t = (new FfmpegCommandBuilder)->timeline($this->plan(['slide_duration' => 2, 'transitions' => ['fade'], 'transition_duration' => 0.5]));

        $this->assertSame(8.0, $t['total']);
        $this->assertSame([2.0, 4.0, 6.0], array_column($t['boundaries'], 'offset'));
        $this->assertSame([2.5, 2.5, 2.5, 2.0], $t['clips']);
    }

    public function test_beat_sync_snaps_slide_to_whole_beats(): void
    {
        $t = (new FfmpegCommandBuilder)->timeline($this->plan(['slide_duration' => 2.2], bpm: 120));

        $this->assertSame(2.0, $t['slide']); // 4 beats of 0.5s
    }

    public function test_duration_limit_is_respected(): void
    {
        $t = (new FfmpegCommandBuilder)->timeline($this->plan(['slide_duration' => 3.5, 'max_images' => 30], images: 30, bpm: 100, max: 60));

        $this->assertLessThanOrEqual(60, $t['total']);
    }

    public function test_hard_cuts_use_concat(): void
    {
        $args = (new FfmpegCommandBuilder)->build($this->plan(['transitions' => ['cut']]));
        $graph = $args[array_search('-filter_complex', $args) + 1];

        $this->assertStringContainsString('concat=n=4:v=1:a=0[joined]', $graph);
        $this->assertStringNotContainsString('xfade', $graph);
    }

    public function test_builds_full_graph(): void
    {
        $args = (new FfmpegCommandBuilder('ffmpeg'))->build($this->plan([
            'transitions' => ['slideleft', 'circleopen'], 'motion' => 'mixed', 'filter' => 'vintage', 'layout' => 'blur',
        ]));
        $graph = $args[array_search('-filter_complex', $args) + 1];

        $this->assertSame('ffmpeg', $args[0]);
        $this->assertStringContainsString('xfade=transition=slideleft', $graph);
        $this->assertStringContainsString('xfade=transition=circleopen', $graph);
        $this->assertStringContainsString('gblur', $graph);
        $this->assertStringContainsString('curves=preset=vintage', $graph);
        $this->assertStringContainsString('[4:a]apad', $graph);
        $this->assertSame('/tmp/out.mp4', end($args));
    }

    public function test_rejects_unknown_style(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TemplateSpec::fromArray(['filter' => 'nope']);
    }
}

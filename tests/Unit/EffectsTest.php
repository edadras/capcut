<?php

namespace ReelMaker\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReelMaker\Rendering\FfmpegCommandBuilder;
use ReelMaker\Rendering\RenderPlan;
use ReelMaker\Rendering\TemplateSpec;
use ReelMaker\Support\Catalog;

class EffectsTest extends TestCase
{
    private function graph(array $config, int $images = 3): string
    {
        $plan = new RenderPlan(
            spec: TemplateSpec::fromArray($config),
            images: array_map(fn ($i) => "/tmp/img$i.jpg", range(1, $images)),
            output: '/tmp/out.mp4',
            textFile: '/tmp/caption.txt',
            fontFile: '/tmp/font.ttf',
            bpm: 120,
        );
        $args = (new FfmpegCommandBuilder)->build($plan);

        return $args[array_search('-filter_complex', $args) + 1];
    }

    public function test_effect_cuts_are_hard_cuts_on_the_beat(): void
    {
        $t = (new FfmpegCommandBuilder)->timeline(new RenderPlan(
            spec: TemplateSpec::fromArray(['transitions' => ['zoom_in', 'whip_left'], 'slide_duration' => 1]),
            images: ['/a', '/b', '/c'],
            output: '/o.mp4',
            bpm: 120,
        ));

        $this->assertTrue($t['all_cut']);
        $this->assertSame([1.0, 2.0], array_column($t['boundaries'], 'offset'));
        $this->assertSame(['zoom_in', 'whip_left'], array_column($t['boundaries'], 'fx'));
    }

    public function test_effect_windows_are_rendered_as_separate_motion_blurred_segments(): void
    {
        $g = $this->graph(['transitions' => ['spin'], 'slide_duration' => 1]);

        $this->assertStringContainsString('split=2', $g);
        $this->assertStringContainsString('fps=120', $g);
        $this->assertStringContainsString("tmix=frames=4,select='eq(mod(n,4),3)'", $g);
        $this->assertStringContainsString("rotate=a='0.6*pow(", $g);
        $this->assertStringContainsString("rotate=a='-0.6*pow(", $g);
    }

    public function test_every_effect_produces_its_filters(): void
    {
        $expect = [
            'whip_left' => 'dblur=angle=0',
            'whip_up' => 'dblur=angle=90',
            'glitch' => 'rgbashift',
            'flash' => "eq=brightness='min(1,0.9*pow(",
            'blur' => 'gblur=sigma=18',
            'shake' => '0.04*ow*sin(',
        ];
        foreach ($expect as $fx => $needle) {
            $this->assertStringContainsString($needle, $this->graph(['transitions' => [$fx], 'slide_duration' => 1]), $fx);
        }
    }

    public function test_beat_effects_overlays_and_text_animation(): void
    {
        $g = $this->graph([
            'transitions' => ['fade'], 'beat_effect' => 'pulse', 'overlay' => 'light_leak',
            'text_style' => 'center', 'text_animation' => 'pop',
        ]);

        $this->assertStringContainsString('exp(-14*mod(', $g);
        $this->assertStringContainsString('gradients=', $g);
        $this->assertStringContainsString('overlay=format=auto', $g);
        $this->assertStringContainsString('2.70158', $g); // easeOutBack pop
        $this->assertStringContainsString('fade=t=out', $g);
    }

    public function test_all_effects_are_known_to_the_spec(): void
    {
        foreach (array_keys(Catalog::FX_TRANSITIONS) as $fx) {
            $this->assertNotEmpty($this->graph(['transitions' => [$fx], 'intro' => $fx]));
        }
    }
}

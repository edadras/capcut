<?php

namespace ReelMaker\Rendering;

/**
 * Everything needed to turn a template + user photos into one video file.
 */
final class RenderPlan
{
    /**
     * @param  list<string>  $images  absolute paths, in display order
     */
    public function __construct(
        public readonly TemplateSpec $spec,
        public readonly array $images,
        public readonly string $output,
        public readonly ?string $audio = null,
        public readonly float $audioStart = 0.0,
        public readonly ?float $bpm = null,
        public readonly ?string $textFile = null,
        public readonly ?string $fontFile = null,
        public readonly int $width = 1080,
        public readonly int $height = 1920,
        public readonly int $fps = 30,
        public readonly float $prescale = 1.5,
        public readonly int $crf = 21,
        public readonly string $preset = 'veryfast',
        public readonly ?float $maxDuration = null,
        public readonly bool $centerText = true,
    ) {
    }
}

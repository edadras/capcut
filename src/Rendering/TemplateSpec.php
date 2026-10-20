<?php

namespace ReelMaker\Rendering;

use InvalidArgumentException;
use ReelMaker\Support\Catalog;

/**
 * Normalised, validated view of a template's JSON config.
 */
final class TemplateSpec
{
    /**
     * @param  list<string>  $transitions  cycled across clip boundaries
     */
    public function __construct(
        public readonly string $format = 'reel',
        public readonly string $layout = 'fill',
        public readonly float $slideDuration = 2.0,
        public readonly array $transitions = ['fade'],
        public readonly float $transitionDuration = 0.5,
        public readonly string $motion = 'zoom_in',
        public readonly string $filter = 'none',
        public readonly string $textStyle = 'none',
        public readonly bool $beatSync = true,
        public readonly int $minImages = 3,
        public readonly int $maxImages = 10,
        public readonly int $variant = 0,
    ) {
        if (! isset(Catalog::LAYOUTS[$layout])) {
            throw new InvalidArgumentException("Unknown layout [$layout].");
        }
        if (! isset(Catalog::MOTIONS[$motion])) {
            throw new InvalidArgumentException("Unknown motion [$motion].");
        }
        if (! isset(Catalog::FILTERS[$filter])) {
            throw new InvalidArgumentException("Unknown filter [$filter].");
        }
        if (! isset(Catalog::TEXT_STYLES[$textStyle])) {
            throw new InvalidArgumentException("Unknown text style [$textStyle].");
        }
        if ($transitions === [] || array_diff($transitions, Catalog::TRANSITIONS) !== []) {
            throw new InvalidArgumentException('Invalid transitions: '.implode(',', $transitions));
        }
        if ($slideDuration < 0.3 || $minImages < 1 || $maxImages < $minImages) {
            throw new InvalidArgumentException('Invalid timing or image bounds.');
        }
    }

    public static function fromArray(array $c): self
    {
        return new self(
            format: $c['format'] ?? 'reel',
            layout: $c['layout'] ?? 'fill',
            slideDuration: (float) ($c['slide_duration'] ?? 2.0),
            transitions: array_values((array) ($c['transitions'] ?? ['fade'])),
            transitionDuration: (float) ($c['transition_duration'] ?? 0.5),
            motion: $c['motion'] ?? 'zoom_in',
            filter: $c['filter'] ?? 'none',
            textStyle: $c['text_style'] ?? 'none',
            beatSync: (bool) ($c['beat_sync'] ?? true),
            minImages: (int) ($c['min_images'] ?? 3),
            maxImages: (int) ($c['max_images'] ?? 10),
            variant: (int) ($c['variant'] ?? 0),
        );
    }

    public function toArray(): array
    {
        return [
            'format' => $this->format,
            'layout' => $this->layout,
            'slide_duration' => $this->slideDuration,
            'transitions' => $this->transitions,
            'transition_duration' => $this->transitionDuration,
            'motion' => $this->motion,
            'filter' => $this->filter,
            'text_style' => $this->textStyle,
            'beat_sync' => $this->beatSync,
            'min_images' => $this->minImages,
            'max_images' => $this->maxImages,
            'variant' => $this->variant,
        ];
    }
}

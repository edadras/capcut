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
        public readonly string $beatEffect = 'none',
        public readonly string $overlay = 'none',
        public readonly string $textAnimation = 'fade',
        public readonly ?string $intro = null,
        public readonly bool $fadeOut = true,
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
        if (! isset(Catalog::BEAT_EFFECTS[$beatEffect])) {
            throw new InvalidArgumentException("Unknown beat effect [$beatEffect].");
        }
        if (! isset(Catalog::OVERLAYS[$overlay])) {
            throw new InvalidArgumentException("Unknown overlay [$overlay].");
        }
        if (! isset(Catalog::TEXT_ANIMATIONS[$textAnimation])) {
            throw new InvalidArgumentException("Unknown text animation [$textAnimation].");
        }
        if ($intro !== null && ! isset(Catalog::FX_TRANSITIONS[$intro])) {
            throw new InvalidArgumentException("Unknown intro [$intro].");
        }
        $known = array_merge(Catalog::TRANSITIONS, array_keys(Catalog::FX_TRANSITIONS));
        if ($transitions === [] || array_diff($transitions, $known) !== []) {
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
            beatEffect: $c['beat_effect'] ?? 'none',
            overlay: $c['overlay'] ?? 'none',
            textAnimation: $c['text_animation'] ?? 'fade',
            intro: $c['intro'] ?? null,
            fadeOut: (bool) ($c['fade_out'] ?? true),
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
            'beat_effect' => $this->beatEffect,
            'overlay' => $this->overlay,
            'text_animation' => $this->textAnimation,
            'intro' => $this->intro,
            'fade_out' => $this->fadeOut,
        ];
    }
}

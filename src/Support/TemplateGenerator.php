<?php

namespace ReelMaker\Support;

use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Produces large, deterministic catalogs of templates by combining
 * category style rules with transitions, motions, color grades and music.
 *
 * Same seed => same catalog, so re-running the generator is idempotent.
 */
final class TemplateGenerator
{
    /** Photo-count bounds per pacing (keeps every template under the duration limit). */
    private const IMAGE_BOUNDS = [
        'flash' => [6, 30],
        'fast' => [4, 25],
        'normal' => [3, 15],
        'slow' => [2, 10],
    ];

    private const TRANSITION_GROUPS = [
        'soft' => ['fade', 'dissolve', 'fadeblack', 'fadewhite', 'hblur', 'distance'],
        'slide' => ['slideleft', 'slideright', 'slideup', 'slidedown', 'smoothleft', 'smoothright', 'smoothup', 'smoothdown', 'coverleft', 'revealright'],
        'wipe' => ['wipeleft', 'wiperight', 'wipeup', 'wipedown', 'diagtl', 'diagbr', 'radial'],
        'shape' => ['circleopen', 'circleclose', 'circlecrop', 'rectcrop', 'vertopen', 'vertclose', 'horzopen', 'horzclose'],
        'glitch' => ['pixelize', 'zoomin', 'squeezev', 'squeezeh'],
        // CapCut-style effect cuts
        'punch' => ['zoom_in', 'zoom_spin', 'zoom_in', 'flash'],
        'whip' => ['whip_left', 'whip_right', 'whip_up', 'whip_down'],
        'energy' => ['zoom_in', 'whip_left', 'whip_right', 'spin', 'flash', 'glitch', 'shake', 'zoom_spin'],
        'dreamy' => ['blur', 'flash', 'fadewhite', 'dissolve', 'zoom_in'],
    ];

    /** Transition groups each energy level draws from (repeats = weight). */
    private const GROUPS_BY_ENERGY = [
        'high' => ['punch', 'whip', 'energy', 'energy', 'glitch', 'slide'],
        'medium' => ['punch', 'whip', 'slide', 'wipe', 'shape', 'dreamy', 'soft'],
        'low' => ['soft', 'soft', 'dreamy', 'dreamy', 'slide', 'shape'],
    ];

    private const HIGH_ENERGY = ['trend', 'sport', 'fashion', 'friends', 'birthday'];

    private const LOW_ENERGY = ['love', 'memories', 'wedding', 'family', 'aesthetic', 'yalda', 'nature'];

    /**
     * @param  array<string, list<array{id: int, mood: string}>>  $tracksByMood
     * @param  list<string>|null  $categories
     * @return \Generator<int, array<string, mixed>>
     */
    public function generate(int $count, array $tracksByMood = [], ?array $categories = null, int $seed = 1): \Generator
    {
        $categories ??= array_keys(Catalog::CATEGORIES);
        $rand = new Randomizer(new Mt19937($seed));
        $seen = [];
        $trackCursor = [];
        $attempts = 0;
        $made = 0;

        while ($made < $count && $attempts < $count * 20) {
            $attempts++;
            $category = $categories[$made % count($categories)];
            $rules = Catalog::CATEGORIES[$category];

            $pacing = $this->pick($rand, $rules['pacing']);
            $filter = $rand->getInt(1, 5) === 1
                ? $this->pick($rand, array_keys(Catalog::FILTERS))
                : $this->pick($rand, $rules['filters']);
            $motion = $this->pick($rand, array_keys(Catalog::MOTIONS));
            $layout = $rand->getInt(1, 3) === 1 ? 'blur' : 'fill';
            $format = $rand->getInt(1, 3) === 1 ? 'story' : 'reel';
            $textStyle = $this->pick($rand, array_keys(Catalog::TEXT_STYLES));
            $energy = in_array($category, self::HIGH_ENERGY, true) ? 'high' : (in_array($category, self::LOW_ENERGY, true) ? 'low' : 'medium');
            $transitions = $this->transitions($rand, $pacing, $energy);
            $fx = $this->effects($rand, $energy);
            [$minImages, $maxImages] = self::IMAGE_BOUNDS[$pacing];

            $mood = $this->pick($rand, $rules['moods']);
            $trackId = null;
            $pool = $mood;
            if (empty($tracksByMood[$pool]) && $tracksByMood !== []) {
                // No music for this mood yet: borrow from the whole library.
                $pool = '*';
                $tracksByMood['*'] ??= array_merge(...array_values($tracksByMood));
            }
            if (! empty($tracksByMood[$pool])) {
                $list = $tracksByMood[$pool];
                $trackCursor[$pool] = (($trackCursor[$pool] ?? $rand->getInt(0, count($list) - 1)) + 1) % count($list);
                $trackId = $list[$trackCursor[$pool]]['id'];
            }

            $config = [
                'format' => $format,
                'layout' => $layout,
                'slide_duration' => Catalog::PACING[$pacing],
                'transitions' => $transitions,
                'transition_duration' => $pacing === 'slow' ? 0.8 : ($pacing === 'normal' ? 0.5 : 0.3),
                'motion' => $motion,
                'filter' => $filter,
                'text_style' => $textStyle,
                'beat_sync' => true,
                'min_images' => $minImages,
                'max_images' => $maxImages,
                'variant' => $rand->getInt(0, 5),
                'pacing' => $pacing,
                'mood' => $mood,
            ] + $fx;

            $key = md5(json_encode([$category, $config, $trackId]));
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $made++;

            yield [
                'slug' => $category.'-'.substr($key, 0, 10),
                'name' => $this->name($category, $config),
                'category' => $category,
                'format' => $format,
                'track_id' => $trackId,
                'config' => $config,
                'min_images' => $minImages,
                'max_images' => $maxImages,
            ];
        }
    }

    /**
     * @return list<string>
     */
    private function transitions(Randomizer $rand, string $pacing, string $energy): array
    {
        // Fast edits often use hard cuts, like most trending templates.
        if (($pacing === 'flash' && $rand->getInt(1, 3) === 1) || $rand->getInt(1, 12) === 1) {
            return ['cut'];
        }

        $group = self::TRANSITION_GROUPS[$this->pick($rand, self::GROUPS_BY_ENERGY[$energy])];
        $group = array_values(array_unique($group));
        $n = $rand->getInt(1, 3);
        $picked = $rand->pickArrayKeys($group, min($n, count($group)));

        return array_values(array_map(fn ($k) => $group[$k], $picked));
    }

    /**
     * Beat effect, overlay, caption animation and intro, weighted by energy.
     */
    private function effects(Randomizer $rand, string $energy): array
    {
        $beat = match ($energy) {
            'high' => ['pulse', 'pulse', 'shake', 'flash', 'none'],
            'medium' => ['pulse', 'none', 'none', 'flash'],
            default => ['none', 'none', 'none', 'pulse'],
        };
        $overlay = match ($energy) {
            'high' => ['none', 'none', 'grain', 'light_leak'],
            'medium' => ['none', 'light_leak', 'grain', 'letterbox'],
            default => ['light_leak', 'light_leak', 'film', 'letterbox', 'none'],
        };
        $intro = $energy === 'low' ? ['blur', 'flash', null, null] : ['zoom_in', 'flash', 'spin', null, null];

        return [
            'beat_effect' => $this->pick($rand, $beat),
            'overlay' => $this->pick($rand, $overlay),
            'text_animation' => $this->pick($rand, array_keys(Catalog::TEXT_ANIMATIONS)),
            'intro' => $this->pick($rand, $intro),
        ];
    }

    private function name(string $category, array $c): string
    {
        $fx = array_values(array_intersect($c['transitions'], array_keys(Catalog::FX_TRANSITIONS)));
        $style = $fx !== [] ? Catalog::FX_TRANSITIONS[$fx[0]] : Catalog::MOTIONS[$c['motion']];

        return sprintf('%s · %s · %s', Catalog::CATEGORIES[$category]['label'], $style, Catalog::FILTERS[$c['filter']][0]);
    }

    private function pick(Randomizer $rand, array $items): mixed
    {
        return $items[$rand->getInt(0, count($items) - 1)];
    }
}

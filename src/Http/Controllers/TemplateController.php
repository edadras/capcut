<?php

namespace ReelMaker\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use ReelMaker\Models\Template;
use ReelMaker\Models\Track;
use ReelMaker\Support\Catalog;

class TemplateController extends Controller
{
    public function index(Request $request)
    {
        $templates = $this->query($request)->paginate(config('reel-maker.per_page'))->withQueryString();

        if ($request->wantsJson()) {
            return $templates->through(fn (Template $t) => $this->present($t));
        }

        return view('reel-maker::templates.index', [
            'templates' => $templates,
            'categories' => Catalog::CATEGORIES,
        ]);
    }

    public function show(Request $request, Template $template)
    {
        abort_unless($template->is_active, 404);
        $template->load('track');

        $tracks = Track::active()->files()
            ->orderByRaw('mood = ? desc', [$template->config['mood'] ?? $template->track?->mood])
            ->orderBy('title')
            ->get(['id', 'title', 'artist', 'mood', 'path']);

        // Trending Instagram sounds: rendered silent, added by the user inside Instagram.
        $trending = Track::active()->instagram()
            ->orderByRaw('mood = ? desc', [$template->config['mood'] ?? $template->track?->mood])
            ->orderByDesc('trending_at')
            ->orderBy('id')
            ->get(['id', 'title', 'artist', 'mood', 'region', 'bpm']);

        if ($request->wantsJson()) {
            return $this->present($template) + [
                'tracks' => $tracks->map->only(['id', 'title', 'artist', 'mood']),
                'instagram_sounds' => $trending->map->only(['id', 'title', 'artist', 'mood', 'region', 'bpm']),
            ];
        }

        return view('reel-maker::templates.show', compact('template', 'tracks', 'trending'));
    }

    private function query(Request $request)
    {
        return Template::active()
            ->with('track:id,title,artist,mood')
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->string('category')))
            ->when($request->filled('format'), fn ($q) => $q->where('format', $request->string('format')))
            ->when($request->filled('mood'), fn ($q) => $q->where('config->mood', $request->string('mood')))
            ->when($request->filled('photos'), fn ($q) => $q
                ->where('min_images', '<=', $request->integer('photos'))
                ->where('max_images', '>=', $request->integer('photos')))
            ->when(
                $request->input('sort') === 'new',
                fn ($q) => $q->latest('id'),
                fn ($q) => $q->orderByDesc('is_featured')->orderByDesc('uses')->orderBy('id'),
            );
    }

    private function present(Template $t): array
    {
        return [
            'slug' => $t->slug,
            'name' => $t->name,
            'category' => $t->category,
            'format' => $t->format,
            'min_images' => $t->min_images,
            'max_images' => $t->max_images,
            'preview_url' => $t->preview_path ? Storage::disk(config('reel-maker.output_disk'))->url($t->preview_path) : null,
            'track' => $t->track?->only(['id', 'title', 'artist', 'mood']),
            'style' => array_intersect_key($t->config, array_flip(['layout', 'motion', 'filter', 'transitions', 'pacing', 'text_style'])),
            'uses' => $t->uses,
        ];
    }
}

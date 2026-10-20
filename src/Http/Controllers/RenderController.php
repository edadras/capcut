<?php

namespace ReelMaker\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use ReelMaker\Http\Requests\CreateRenderRequest;
use ReelMaker\Models\Render;
use ReelMaker\Models\Template;
use ReelMaker\Support\RenderService;

class RenderController extends Controller
{
    public function __construct(private readonly RenderService $service)
    {
    }

    public function store(CreateRenderRequest $request, Template $template)
    {
        abort_unless($template->is_active, 404);

        $render = $this->service->create(
            $template,
            $request->file('images'),
            $request->input('caption'),
            $request->filled('track_id') ? $request->integer('track_id') : null,
            $request->user()?->getAuthIdentifier(),
        );

        if ($request->wantsJson()) {
            return response()->json($render->toApi(), 202);
        }

        return redirect()->route('reel-maker.renders.show', $render);
    }

    public function show(Request $request, Render $render)
    {
        abort_unless($this->service->canView($render, $request->user()?->getAuthIdentifier()), 404);
        $render->load('template');

        if ($request->wantsJson()) {
            return $render->toApi();
        }

        return view('reel-maker::renders.show', compact('render'));
    }
}

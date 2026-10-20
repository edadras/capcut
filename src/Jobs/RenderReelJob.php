<?php

namespace ReelMaker\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use ReelMaker\Models\Render;
use ReelMaker\Rendering\Renderer;
use Throwable;

class RenderReelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public Render $render)
    {
        $this->timeout = (int) config('reel-maker.timeout') + 60;
        $this->onQueue(config('reel-maker.queue'));
        $this->onConnection(config('reel-maker.connection'));
    }

    public function handle(Renderer $renderer): void
    {
        $this->render->forceFill(['status' => Render::PROCESSING, 'started_at' => now(), 'error' => null])->save();

        $renderer->render($this->render);

        $this->render->forceFill(['status' => Render::DONE, 'finished_at' => now()])->save();
        $this->render->template()->increment('uses');
    }

    public function failed(Throwable $e): void
    {
        $this->render->forceFill([
            'status' => Render::FAILED,
            'error' => mb_substr($e->getMessage(), 0, 2000),
            'finished_at' => now(),
        ])->save();
    }
}

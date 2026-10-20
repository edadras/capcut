<?php

namespace ReelMaker\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use ReelMaker\Jobs\RenderReelJob;
use ReelMaker\Models\Render;
use ReelMaker\Models\Template;

class RenderService
{
    /**
     * Store the uploaded photos and queue the render.
     *
     * @param  list<UploadedFile>  $images
     */
    public function create(Template $template, array $images, ?string $caption, ?int $trackId, ?int $userId): Render
    {
        $uuid = (string) Str::uuid();
        $dir = config('reel-maker.directory')."/uploads/$uuid";
        $disk = config('reel-maker.upload_disk');

        $paths = [];
        foreach (array_values($images) as $i => $file) {
            $paths[] = $file->storeAs($dir, sprintf('%02d.%s', $i, $file->extension() ?: 'jpg'), $disk);
        }

        $render = Render::create([
            'uuid' => $uuid,
            'user_id' => $userId,
            'template_id' => $template->id,
            'track_id' => $trackId ?? $template->track_id,
            'images' => $paths,
            'caption' => $caption,
            'status' => Render::PENDING,
        ]);

        RenderReelJob::dispatch($render);

        return $render;
    }

    public function canView(Render $render, ?int $userId): bool
    {
        return $render->user_id === null || $render->user_id === $userId;
    }
}

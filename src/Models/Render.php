<?php

namespace ReelMaker\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Render extends Model
{
    public const PENDING = 'pending';
    public const PROCESSING = 'processing';
    public const DONE = 'done';
    public const FAILED = 'failed';

    protected $table = 'reel_renders';

    protected $guarded = ['id'];

    protected $casts = [
        'images' => 'array',
        'duration' => 'float',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(fn (Render $r) => $r->uuid ??= (string) Str::uuid());
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
    }

    public function outputUrl(): ?string
    {
        return $this->output_path
            ? Storage::disk(config('reel-maker.output_disk'))->url($this->output_path)
            : null;
    }

    public function toApi(): array
    {
        return [
            'id' => $this->uuid,
            'status' => $this->status,
            'template' => $this->template?->slug,
            'duration' => $this->duration,
            'url' => $this->status === self::DONE ? $this->outputUrl() : null,
            'error' => $this->status === self::FAILED ? 'ساخت ویدیو ناموفق بود. دوباره تلاش کنید.' : null,
        ];
    }
}

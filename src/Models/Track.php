<?php

namespace ReelMaker\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Track extends Model
{
    protected $table = 'reel_tracks';

    protected $guarded = ['id'];

    protected $casts = [
        'duration' => 'float',
        'bpm' => 'float',
        'highlight_start' => 'float',
        'is_active' => 'boolean',
    ];

    public function templates(): HasMany
    {
        return $this->hasMany(Template::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    /**
     * Attribution line required by CC BY licenses, e.g. for the post caption.
     */
    public function credit(): ?string
    {
        if (! $this->license) {
            return null;
        }

        return trim("🎵 {$this->title}".($this->artist ? " — {$this->artist}" : '')." ({$this->license})"
            .($this->source_url ? " {$this->source_url}" : ''));
    }

    public function absolutePath(): string
    {
        return Storage::disk(config('reel-maker.music_disk'))->path($this->path);
    }
}

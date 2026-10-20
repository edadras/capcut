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
        'links' => 'array',
        'trending_at' => 'date',
    ];

    public const FILE = 'file';

    /** Sound the user adds inside Instagram (no file stored, video rendered silent). */
    public const INSTAGRAM = 'instagram';

    public function templates(): HasMany
    {
        return $this->hasMany(Template::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeFiles(Builder $q): Builder
    {
        return $q->where('kind', self::FILE);
    }

    public function scopeInstagram(Builder $q): Builder
    {
        return $q->where('kind', self::INSTAGRAM);
    }

    public function isInstagram(): bool
    {
        return $this->kind === self::INSTAGRAM;
    }

    /**
     * What to show the user for an Instagram sound: search term, start point and listen links.
     */
    public function instagramSound(): ?array
    {
        if (! $this->isInstagram()) {
            return null;
        }

        return [
            'title' => $this->title,
            'artist' => $this->artist,
            'search' => trim($this->title.' '.$this->artist),
            'start' => $this->highlight_start > 0 ? gmdate('i:s', (int) $this->highlight_start) : null,
            'bpm' => $this->bpm,
            'links' => $this->links ?? [],
        ];
    }

    /**
     * Attribution line required by CC BY licenses, e.g. for the post caption.
     */
    public function credit(): ?string
    {
        if (! $this->license || $this->isInstagram()) {
            return null;
        }

        return trim("🎵 {$this->title}".($this->artist ? " — {$this->artist}" : '')." ({$this->license})"
            .($this->source_url ? " {$this->source_url}" : ''));
    }

    public function absolutePath(): ?string
    {
        if ($this->path === null) {
            return null;
        }

        return Storage::disk(config('reel-maker.music_disk'))->path($this->path);
    }
}

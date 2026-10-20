<?php

namespace ReelMaker\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ReelMaker\Models\Track;
use ReelMaker\Rendering\AudioAnalyzer;

/**
 * Copies an audio file into the music library and records duration, tempo and best start point.
 */
class TrackImporter
{
    public function __construct(private readonly AudioAnalyzer $analyzer)
    {
    }

    /**
     * @param  array{title: string, artist?: ?string, license?: ?string, license_url?: ?string, source_url?: ?string, external_id?: ?string}  $meta
     */
    public function import(string $file, string $mood, array $meta): Track
    {
        $duration = $this->analyzer->duration($file);
        $highlight = $duration > 45 ? $this->analyzer->highlight($file) : 0.0;
        $bpm = $this->analyzer->bpm($file, $highlight ?: min(20.0, $duration / 4));

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION)) ?: 'mp3';
        $path = config('reel-maker.directory')."/music/$mood/".Str::slug(Str::limit(($meta['artist'] ?? '').' '.$meta['title'], 60, ''))
            .'-'.substr(md5_file($file), 0, 8).'.'.$ext;

        $stream = fopen($file, 'rb');
        Storage::disk(config('reel-maker.music_disk'))->writeStream($path, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }

        $key = ! empty($meta['external_id']) ? ['external_id' => $meta['external_id']] : ['path' => $path];

        return Track::updateOrCreate($key, [
            'title' => trim($meta['title']),
            'artist' => isset($meta['artist']) ? trim($meta['artist']) : null,
            'mood' => $mood,
            'path' => $path,
            'duration' => $duration,
            'bpm' => $bpm,
            'highlight_start' => $highlight,
            'license' => $meta['license'] ?? null,
            'license_url' => $meta['license_url'] ?? null,
            'source_url' => $meta['source_url'] ?? null,
        ]);
    }
}

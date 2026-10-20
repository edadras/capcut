<?php

namespace ReelMaker\Music;

use Illuminate\Support\Facades\Http;

/**
 * Jamendo (https://www.jamendo.com) — 500k+ Creative Commons tracks.
 * Needs a free API client id (https://devportal.jamendo.com). Only CC BY / CC0
 * tracks are kept (no NonCommercial, NoDerivs or ShareAlike).
 */
class JamendoProvider implements MusicProvider
{
    public const TAGS = [
        'energetic' => 'energetic',
        'pop' => 'pop',
        'happy' => 'happy',
        'romantic' => 'romantic',
        'calm' => 'calm',
        'sad' => 'sad',
        'epic' => 'epic',
        'chill' => 'chillout',
        'corporate' => 'corporate',
    ];

    public function __construct(private readonly string $clientId)
    {
    }

    public function name(): string
    {
        return 'jamendo';
    }

    public function search(string $mood, int $limit, int $maxDuration): iterable
    {
        $found = 0;
        for ($offset = 0; $found < $limit && $offset < 2000; $offset += 200) {
            $results = Http::timeout(60)->retry(3, 2000)->get('https://api.jamendo.com/v3.0/tracks/', [
                'client_id' => $this->clientId,
                'format' => 'json',
                'limit' => 200,
                'offset' => $offset,
                'fuzzytags' => self::TAGS[$mood] ?? $mood,
                'audiodownload_allowed' => 'true',
                'ccnc' => 'false',
                'ccnd' => 'false',
                'ccsa' => 'false',
                'durationbetween' => '30_'.$maxDuration,
                'order' => 'popularity_total',
            ])->json('results') ?? [];

            if ($results === []) {
                return;
            }

            foreach ($results as $item) {
                if (($track = $this->map($item)) !== null) {
                    yield $track;
                    if (++$found >= $limit) {
                        return;
                    }
                }
            }
        }
    }

    public function map(array $item): ?RemoteTrack
    {
        $license = $item['license_ccurl'] ?? '';
        $url = $item['audiodownload'] ?? '';
        if ($url === '' || empty($item['audiodownload_allowed']) || ! RemoteTrack::isCommercialFriendly($license)) {
            return null;
        }

        return new RemoteTrack(
            externalId: 'jamendo:'.$item['id'],
            title: $item['name'] ?? 'Untitled',
            artist: $item['artist_name'] ?? null,
            downloadUrl: $url,
            license: str_contains($license, 'publicdomain') ? 'CC0' : 'CC BY',
            licenseUrl: $license,
            sourceUrl: $item['shareurl'] ?? 'https://www.jamendo.com',
            duration: isset($item['duration']) ? (float) $item['duration'] : null,
        );
    }
}

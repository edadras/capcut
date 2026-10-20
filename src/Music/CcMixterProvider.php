<?php

namespace ReelMaker\Music;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * ccMixter (https://ccmixter.org) — community music under Creative Commons.
 * Only "Attribution" (CC BY) uploads are requested: free for commercial use with credit.
 */
class CcMixterProvider implements MusicProvider
{
    public const TAGS = [
        'energetic' => ['dance', 'electronic', 'rock', 'edm'],
        'pop' => ['pop', 'funk'],
        'happy' => ['happy', 'upbeat', 'fun'],
        'romantic' => ['romantic', 'love'],
        'calm' => ['piano', 'acoustic'],
        'sad' => ['sad', 'melancholy'],
        'epic' => ['epic', 'orchestral', 'soundtrack'],
        'chill' => ['chill', 'downtempo', 'lofi'],
        'corporate' => ['instrumental', 'guitar'],
    ];

    public function name(): string
    {
        return 'ccmixter';
    }

    public function search(string $mood, int $limit, int $maxDuration): iterable
    {
        $found = 0;
        $seen = [];

        foreach (self::TAGS[$mood] ?? [$mood] as $tag) {
            for ($offset = 0; $found < $limit && $offset < 1000; $offset += 20) {
                try {
                    $items = Http::timeout(60)->retry(3, 2000)->get('https://ccmixter.org/api/query', [
                        'f' => 'json', 'lic' => 'by', 'tags' => $tag, 'sort' => 'rank', 'limit' => 20, 'offset' => $offset,
                    ])->json() ?? [];
                } catch (Throwable $e) {
                    report($e);

                    continue 2; // try the next tag
                }

                if ($items === []) {
                    break;
                }

                foreach ($items as $item) {
                    $track = $this->map($item, $maxDuration);
                    if ($track === null || isset($seen[$track->externalId])) {
                        continue;
                    }
                    $seen[$track->externalId] = true;
                    yield $track;
                    if (++$found >= $limit) {
                        return;
                    }
                }
            }
        }
    }

    public function map(array $item, int $maxDuration): ?RemoteTrack
    {
        if (! empty($item['upload_extra']['nsfw']) || ! RemoteTrack::isCommercialFriendly($item['license_url'] ?? '')) {
            return null;
        }

        foreach ($item['files'] ?? [] as $file) {
            $info = $file['file_format_info'] ?? [];
            if (($info['default-ext'] ?? '') !== 'mp3' || empty($file['download_url'])) {
                continue;
            }

            $duration = null;
            if (preg_match('/^(\d+):(\d{2})$/', $info['ps'] ?? '', $m)) {
                $duration = $m[1] * 60 + (int) $m[2];
                if ($duration > $maxDuration || $duration < 30) {
                    return null;
                }
            }

            return new RemoteTrack(
                externalId: 'ccmixter:'.$item['upload_id'],
                title: html_entity_decode($item['upload_name'] ?? 'Untitled', ENT_QUOTES),
                artist: html_entity_decode($item['user_real_name'] ?? $item['user_name'] ?? '', ENT_QUOTES) ?: null,
                downloadUrl: $file['download_url'],
                license: 'CC '.($item['license_name'] ?? 'Attribution'),
                licenseUrl: $item['license_url'],
                sourceUrl: $item['file_page_url'] ?? 'https://ccmixter.org',
                duration: $duration,
            );
        }

        return null;
    }
}

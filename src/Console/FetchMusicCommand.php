<?php

namespace ReelMaker\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use ReelMaker\Models\Track;
use ReelMaker\Music\CcMixterProvider;
use ReelMaker\Music\JamendoProvider;
use ReelMaker\Music\MusicProvider;
use ReelMaker\Support\Catalog;
use ReelMaker\Support\TrackImporter;
use Throwable;

class FetchMusicCommand extends Command
{
    protected $signature = 'reel-maker:fetch-music
        {--provider=ccmixter : ccmixter (no key needed) or jamendo (set REEL_MAKER_JAMENDO_CLIENT_ID)}
        {--mood=* : Only these moods (default: all)}
        {--per-mood=20 : Tracks to download per mood}
        {--max-duration=330 : Skip tracks longer than this (seconds)}';

    protected $description = 'Download commercial-friendly Creative Commons music (CC BY / CC0) with attribution';

    public function handle(TrackImporter $importer): int
    {
        $provider = $this->provider();
        if ($provider === null) {
            return self::FAILURE;
        }

        $moods = $this->option('mood') ?: array_keys(Catalog::MOODS);
        $tmp = storage_path('app/reel-maker-tmp/downloads');
        File::ensureDirectoryExists($tmp);
        $total = 0;

        foreach ($moods as $mood) {
            if (! isset(Catalog::MOODS[$mood])) {
                $this->warn("Unknown mood [$mood].");

                continue;
            }

            $this->info("— $mood (".Catalog::MOODS[$mood].')');
            $count = 0;

            foreach ($provider->search($mood, (int) $this->option('per-mood'), (int) $this->option('max-duration')) as $remote) {
                if (Track::where('external_id', $remote->externalId)->exists()) {
                    continue;
                }

                $file = $tmp.'/'.Str::slug($remote->externalId).'.mp3';
                try {
                    // Same headers a browser sends from the track page (hosts block hotlinking).
                    Http::timeout(180)->retry(2, 3000)
                        ->withHeaders(['Referer' => $remote->sourceUrl, 'User-Agent' => 'Mozilla/5.0 (compatible; ReelMaker)'])
                        ->sink($file)->get($remote->downloadUrl)->throw();
                    $track = $importer->import($file, $mood, [
                        'title' => $remote->title,
                        'artist' => $remote->artist,
                        'license' => $remote->license,
                        'license_url' => $remote->licenseUrl,
                        'source_url' => $remote->sourceUrl,
                        'external_id' => $remote->externalId,
                    ]);
                    $count++;
                    $this->line(sprintf('  ✓ %-45s %5.0fs  %s BPM', Str::limit($track->title.' — '.$track->artist, 43), $track->duration, $track->bpm ?? '?'));
                } catch (Throwable $e) {
                    $this->warn("  ✗ {$remote->title}: ".Str::limit($e->getMessage(), 120));
                } finally {
                    @unlink($file);
                }
            }

            $total += $count;
        }

        $this->info("Downloaded $total track(s). Run reel-maker:generate-templates to attach them to templates.");
        $this->comment('These tracks require attribution: credits are shown on the result page and returned by the API.');

        return self::SUCCESS;
    }

    private function provider(): ?MusicProvider
    {
        return match ($this->option('provider')) {
            'ccmixter' => new CcMixterProvider,
            'jamendo' => ($id = config('reel-maker.jamendo_client_id'))
                ? new JamendoProvider($id)
                : tap(null, fn () => $this->error('Set REEL_MAKER_JAMENDO_CLIENT_ID (free at https://devportal.jamendo.com).')),
            default => tap(null, fn () => $this->error('Unknown provider. Use ccmixter or jamendo.')),
        };
    }
}

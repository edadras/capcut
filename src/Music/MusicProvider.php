<?php

namespace ReelMaker\Music;

interface MusicProvider
{
    public function name(): string;

    /**
     * Commercial-friendly tracks (CC BY / CC0) matching a mood.
     *
     * @return iterable<RemoteTrack>
     */
    public function search(string $mood, int $limit, int $maxDuration): iterable;
}

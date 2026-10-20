<?php

namespace ReelMaker\Music;

final class RemoteTrack
{
    public function __construct(
        public readonly string $externalId,
        public readonly string $title,
        public readonly ?string $artist,
        public readonly string $downloadUrl,
        public readonly string $license,
        public readonly string $licenseUrl,
        public readonly string $sourceUrl,
        public readonly ?float $duration = null,
    ) {
    }

    /** Only licenses that allow use in commercial videos without share-alike obligations. */
    public static function isCommercialFriendly(string $licenseUrl): bool
    {
        return (bool) preg_match('~creativecommons\.org/(licenses/by/|publicdomain/zero/)~i', $licenseUrl);
    }
}

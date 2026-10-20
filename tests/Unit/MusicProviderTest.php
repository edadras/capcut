<?php

namespace ReelMaker\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReelMaker\Music\CcMixterProvider;
use ReelMaker\Music\JamendoProvider;
use ReelMaker\Music\RemoteTrack;

class MusicProviderTest extends TestCase
{
    public function test_only_commercial_friendly_licenses_are_accepted(): void
    {
        $this->assertTrue(RemoteTrack::isCommercialFriendly('http://creativecommons.org/licenses/by/3.0/'));
        $this->assertTrue(RemoteTrack::isCommercialFriendly('https://creativecommons.org/publicdomain/zero/1.0/'));
        $this->assertFalse(RemoteTrack::isCommercialFriendly('http://creativecommons.org/licenses/by-nc/3.0/'));
        $this->assertFalse(RemoteTrack::isCommercialFriendly('http://creativecommons.org/licenses/by-sa/4.0/'));
        $this->assertFalse(RemoteTrack::isCommercialFriendly('http://creativecommons.org/licenses/by-nd/4.0/'));
    }

    public function test_maps_ccmixter_uploads(): void
    {
        $item = [
            'upload_id' => 54335, 'upload_name' => 'Hanging Eleven', 'user_real_name' => 'Kara Square',
            'license_url' => 'http://creativecommons.org/licenses/by/3.0/', 'license_name' => 'Attribution (3.0)',
            'file_page_url' => 'https://ccmixter.org/files/mindmapthat/54335', 'upload_extra' => ['nsfw' => false],
            'files' => [
                ['download_url' => 'https://ccmixter.org/content/x.mp3', 'file_format_info' => ['default-ext' => 'mp3', 'ps' => '2:27']],
            ],
        ];
        $track = (new CcMixterProvider)->map($item, 300);

        $this->assertSame('ccmixter:54335', $track->externalId);
        $this->assertSame('Kara Square', $track->artist);
        $this->assertSame(147.0, (float) $track->duration);
        $this->assertNull((new CcMixterProvider)->map($item, 120)); // too long
        $this->assertNull((new CcMixterProvider)->map(['license_url' => 'http://creativecommons.org/licenses/by-nc/3.0/'] + $item, 300));
    }

    public function test_maps_jamendo_tracks(): void
    {
        $p = new JamendoProvider('key');
        $item = ['id' => '1', 'name' => 'Song', 'artist_name' => 'Band', 'audiodownload' => 'https://x/y.mp3', 'audiodownload_allowed' => true,
            'license_ccurl' => 'http://creativecommons.org/licenses/by/3.0/', 'shareurl' => 'https://jamen.do/t/1', 'duration' => 120];

        $this->assertSame('jamendo:1', $p->map($item)->externalId);
        $this->assertNull($p->map(['license_ccurl' => 'http://creativecommons.org/licenses/by-nc-sa/3.0/'] + $item));
        $this->assertNull($p->map(['audiodownload_allowed' => false] + $item));
    }
}

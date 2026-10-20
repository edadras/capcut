<?php

namespace ReelMaker\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReelMaker\Support\Catalog;

class TrendingDataTest extends TestCase
{
    public function test_bundled_trending_list_is_valid(): void
    {
        $data = json_decode(file_get_contents(__DIR__.'/../../resources/data/instagram-trending.json'), true);

        $this->assertCount(65, $data['sounds']);
        foreach ($data['sounds'] as $s) {
            $this->assertNotEmpty($s['title']);
            $this->assertArrayHasKey($s['mood'], Catalog::MOODS, $s['title']);
            $this->assertContains($s['region'], ['global', 'turkey', 'rising', 'autumn', 'persian']);
            $this->assertNotEmpty($s['links']);
            foreach ($s['links'] as $url) {
                $this->assertMatchesRegularExpression('~^https://(open\.spotify\.com|www\.youtube\.com|www\.radiojavan\.com)/~', $url);
            }
        }
    }
}

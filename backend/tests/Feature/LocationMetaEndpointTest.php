<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationMetaEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_locations_endpoint_returns_prefix_matches_with_limit(): void
    {
        $response = $this->getJson('/api/meta/locations?q=Br&limit=8');

        $response->assertOk();
        $rows = $response->json('data');

        $this->assertIsArray($rows);
        $this->assertNotEmpty($rows);
        $this->assertLessThanOrEqual(8, count($rows));
        $this->assertSame('Bratislava, Slovensko', $rows[0]['label']);
        $this->assertSame('sk:bratislava', $rows[0]['place_id']);
    }
}

<?php

namespace Tests\Unit;

use App\Models\User;
use Tests\TestCase;

class UserLocationMetaTest extends TestCase
{
    public function test_user_location_meta_contains_coordinates_for_known_location(): void
    {
        $user = User::factory()->make([
            'location' => 'Bratislava',
        ]);

        $meta = $user->location_meta;

        $this->assertIsArray($meta);
        $this->assertSame(48.1486, $meta['lat']);
        $this->assertSame(17.1077, $meta['lon']);
        $this->assertSame('Europe/Bratislava', $meta['tz']);
    }

    public function test_user_location_meta_returns_null_coords_for_unknown_location(): void
    {
        $user = User::factory()->make([
            'location' => 'Unknown Place',
        ]);

        $meta = $user->location_meta;

        $this->assertIsArray($meta);
        $this->assertNull($meta['lat']);
        $this->assertNull($meta['lon']);
        $this->assertSame('Europe/Bratislava', $meta['tz']);
    }

    public function test_user_location_meta_handles_city_with_country_suffix(): void
    {
        $user = User::factory()->make([
            'location' => 'Bratislava, SK',
        ]);

        $meta = $user->location_meta;

        $this->assertIsArray($meta);
        $this->assertSame(48.1486, $meta['lat']);
        $this->assertSame(17.1077, $meta['lon']);
        $this->assertSame('Europe/Bratislava', $meta['tz']);
    }

    public function test_user_location_meta_prefers_precise_coordinates_when_set(): void
    {
        $user = User::factory()->make([
            'location' => 'Anywhere',
            'latitude' => 49.1234567,
            'longitude' => 18.7654321,
            'timezone' => 'Europe/Prague',
        ]);

        $meta = $user->location_meta;

        $this->assertIsArray($meta);
        $this->assertSame(49.1234567, $meta['lat']);
        $this->assertSame(18.7654321, $meta['lon']);
        $this->assertSame('Europe/Prague', $meta['tz']);
    }
}

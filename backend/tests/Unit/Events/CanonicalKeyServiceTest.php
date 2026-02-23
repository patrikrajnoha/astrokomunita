<?php

namespace Tests\Unit\Events;

use App\Services\Events\CanonicalKeyService;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class CanonicalKeyServiceTest extends TestCase
{
    public function test_make_normalizes_type_title_and_date_deterministically(): void
    {
        $service = new CanonicalKeyService();

        $key = $service->make(
            type: 'Meteor_Shower',
            date: CarbonImmutable::parse('2026-04-22 23:30:00', 'Europe/Bratislava'),
            title: '  Lyrids (LYR)  '
        );

        $this->assertSame('meteor shower|2026-04-22|lyrids lyr', $key);
    }

    public function test_make_without_date_still_returns_stable_key(): void
    {
        $service = new CanonicalKeyService();

        $key = $service->make('other', null, 'Gamma-Ray Burst Alert');

        $this->assertSame('other|gamma ray burst alert', $key);
    }
}


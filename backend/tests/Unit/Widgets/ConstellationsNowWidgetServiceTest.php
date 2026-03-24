<?php

namespace Tests\Unit\Widgets;

use App\Services\Widgets\ConstellationsNowWidgetService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ConstellationsNowWidgetServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        Cache::flush();

        parent::tearDown();
    }

    public function test_payload_returns_three_to_five_items_with_expected_contract(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-01-15 20:30:00', 'Europe/Bratislava'));

        $service = app(ConstellationsNowWidgetService::class);
        $payload = $service->payload();

        $this->assertTrue((bool) ($payload['available'] ?? false));
        $this->assertIsArray($payload['items'] ?? null);
        $this->assertGreaterThanOrEqual(3, count($payload['items']));
        $this->assertLessThanOrEqual(5, count($payload['items']));
        $this->assertTrue((bool) ($payload['meta']['default_location_used'] ?? false));

        $names = array_values(array_map(
            static fn (array $item): string => (string) ($item['name'] ?? ''),
            $payload['items']
        ));

        $winterCandidates = ['Orion', 'Gemini', 'Taurus', 'Auriga', 'Canis Major'];
        $this->assertNotEmpty(array_intersect($winterCandidates, $names));
    }

    public function test_payload_uses_passed_context_and_prefers_summer_constellations_in_july(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-20 22:10:00', 'Europe/Bratislava'));

        $service = app(ConstellationsNowWidgetService::class);
        $payload = $service->payload([
            'lat' => 48.1486,
            'lon' => 17.1077,
            'tz' => 'Europe/Bratislava',
        ]);

        $this->assertFalse((bool) ($payload['meta']['default_location_used'] ?? true));

        $names = array_values(array_map(
            static fn (array $item): string => (string) ($item['name'] ?? ''),
            $payload['items']
        ));

        $this->assertTrue(
            in_array('Cygnus', $names, true)
            || in_array('Lyra', $names, true)
            || in_array('Aquila', $names, true)
        );
    }

    public function test_payload_includes_evening_cloud_percent_when_weather_payload_is_passed(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-03-21 21:10:00', 'Europe/Bratislava'));

        $service = app(ConstellationsNowWidgetService::class);
        $payload = $service->payload(
            [
                'lat' => 48.1486,
                'lon' => 17.1077,
                'tz' => 'Europe/Bratislava',
            ],
            [
                'cloud_percent' => 44,
                'evening_cloud_percent' => 86,
            ]
        );

        $this->assertSame(86, $payload['meta']['evening_cloud_percent'] ?? null);
    }
}

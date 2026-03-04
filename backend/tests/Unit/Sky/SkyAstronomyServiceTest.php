<?php

namespace Tests\Unit\Sky;

use App\Services\Observing\Contracts\SunMoonProvider;
use App\Services\Observing\SkyMicroserviceClient;
use App\Services\Sky\SkyAstronomyService;
use Mockery;
use PHPUnit\Framework\TestCase;

class SkyAstronomyServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_includes_sample_at_and_sun_altitude_from_microservice(): void
    {
        $sunMoonProvider = Mockery::mock(SunMoonProvider::class);
        $sunMoonProvider->shouldReceive('get')
            ->once()
            ->andReturn([
                'sunrise' => '07:07',
                'sunset' => '17:05',
                'civil_twilight_end' => '17:40',
                'phase_name' => 'Waning Crescent',
                'fracillum' => 0.78,
            ]);

        $skyMicroserviceClient = Mockery::mock(SkyMicroserviceClient::class);
        $skyMicroserviceClient->shouldReceive('fetch')
            ->once()
            ->andReturn([
                'moon' => [
                    'rise_local' => '20:20',
                    'set_local' => '07:10',
                ],
                'sample_at' => '2026-01-15T00:00:00+01:00',
                'sun_altitude_deg' => -32.44,
                'planets' => [],
            ]);

        $service = new SkyAstronomyService($sunMoonProvider, $skyMicroserviceClient);
        $payload = $service->fetch(48.1486, 17.1077, 'Europe/Bratislava');

        $this->assertSame('2026-01-15T00:00:00+01:00', $payload['sample_at']);
        $this->assertSame(-32.4, $payload['sun_altitude_deg']);
        $this->assertSame('waning_crescent', $payload['moon_phase']);
        $this->assertSame(78, $payload['moon_illumination_percent']);
    }

    public function test_it_keeps_sample_and_altitude_null_when_microservice_fails(): void
    {
        $sunMoonProvider = Mockery::mock(SunMoonProvider::class);
        $sunMoonProvider->shouldReceive('get')
            ->once()
            ->andReturn([
                'sunrise' => '07:07',
                'sunset' => '17:05',
                'civil_twilight_end' => '17:40',
                'phase_name' => 'Waning Crescent',
                'fracillum' => 0.78,
            ]);

        $skyMicroserviceClient = Mockery::mock(SkyMicroserviceClient::class);
        $skyMicroserviceClient->shouldReceive('fetch')
            ->once()
            ->andThrow(new \RuntimeException('sky unavailable'));

        $service = new SkyAstronomyService($sunMoonProvider, $skyMicroserviceClient);
        $payload = $service->fetch(48.1486, 17.1077, 'Europe/Bratislava');

        $this->assertNull($payload['sample_at']);
        $this->assertNull($payload['sun_altitude_deg']);
    }
}

<?php

namespace Tests\Unit;

use App\Services\Observing\OpenMeteoWeatherCodeMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OpenMeteoWeatherCodeMapperTest extends TestCase
{
    #[DataProvider('weatherCodeProvider')]
    public function test_it_maps_wmo_codes_to_slovak_labels(?int $code, string $expected): void
    {
        $mapper = new OpenMeteoWeatherCodeMapper();

        $this->assertSame($expected, $mapper->labelSk($code));
    }

    public static function weatherCodeProvider(): array
    {
        return [
            'clear' => [0, 'Jasno'],
            'partly_cloudy' => [2, 'Polojasno'],
            'fog' => [45, 'Hmla'],
            'rain' => [63, 'Dážď'],
            'snow' => [75, 'Sneženie'],
            'thunderstorm' => [95, 'Búrka'],
            'unknown' => [null, 'Neznáme'],
        ];
    }
}

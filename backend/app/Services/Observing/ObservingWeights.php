<?php

namespace App\Services\Observing;

class ObservingWeights
{
    public const MODE_DEEP_SKY = 'deep_sky';
    public const MODE_PLANETS = 'planets';
    public const MODE_METEORS = 'meteors';

    /**
     * @return array<string,float>
     */
    public function forMode(string $mode): array
    {
        $resolved = $this->sanitizeMode($mode);

        return match ($resolved) {
            self::MODE_PLANETS => [
                'seeing' => 0.25,
                'cloud' => 0.20,
                'humidity' => 0.10,
                'moon' => 0.05,
                'darkness' => 0.10,
                'air_quality' => 0.20,
                'light_pollution' => 0.10,
            ],
            self::MODE_METEORS => [
                'darkness' => 0.20,
                'cloud' => 0.25,
                'humidity' => 0.15,
                'moon' => 0.20,
                'air_quality' => 0.10,
                'light_pollution' => 0.10,
            ],
            default => [
                'humidity' => 0.20,
                'cloud' => 0.25,
                'air_quality' => 0.15,
                'moon' => 0.15,
                'darkness' => 0.05,
                'seeing' => 0.05,
                'light_pollution' => 0.15,
            ],
        };
    }

    public function sanitizeMode(?string $mode): string
    {
        $candidate = strtolower(trim((string) $mode));
        if (in_array($candidate, $this->supportedModes(), true)) {
            return $candidate;
        }

        return self::MODE_DEEP_SKY;
    }

    /**
     * @return list<string>
     */
    public function supportedModes(): array
    {
        return [
            self::MODE_DEEP_SKY,
            self::MODE_PLANETS,
            self::MODE_METEORS,
        ];
    }
}

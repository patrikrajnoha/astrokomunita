<?php

namespace Tests\Unit\Events;

use App\Models\Event;
use App\Services\Events\EventInsightsCacheService;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class EventInsightsCacheServiceTest extends TestCase
{
    public function test_factual_pack_and_hash_input_use_same_key_set(): void
    {
        $event = new Event([
            'title' => 'Mesiac v perigeu: 363000 km',
            'type' => 'other',
            'region_scope' => 'global',
            'source_name' => 'manual',
            'visibility' => 1,
        ]);
        $event->setAttribute('location', 'Bratislava');
        $event->setAttribute('start_at', CarbonImmutable::parse('2026-03-08 20:00:00', 'UTC'));
        $event->setAttribute('max_at', CarbonImmutable::parse('2026-03-08 20:15:00', 'UTC'));
        $event->setAttribute('end_at', CarbonImmutable::parse('2026-03-08 21:00:00', 'UTC'));

        $service = new EventInsightsCacheService();

        $factualPack = $service->buildFactualPackForHash($event);
        $hashInput = $service->buildEventHashInput($event);

        $factualTopKeys = array_keys($factualPack);
        $hashTopKeys = array_keys($hashInput);
        sort($factualTopKeys);
        sort($hashTopKeys);

        $this->assertSame($factualTopKeys, $hashTopKeys);
        $this->assertSame(
            $this->flattenKeyPaths($factualPack),
            $this->flattenKeyPaths($hashInput)
        );
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<int,string>
     */
    private function flattenKeyPaths(array $payload, string $prefix = ''): array
    {
        $paths = [];

        foreach ($payload as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            $paths[] = $path;

            if (is_array($value)) {
                $paths = array_merge($paths, $this->flattenKeyPaths($value, $path));
            }
        }

        $paths = array_values(array_unique($paths));
        sort($paths);

        return $paths;
    }
}

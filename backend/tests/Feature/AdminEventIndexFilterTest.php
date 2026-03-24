<?php

namespace Tests\Feature;

use App\Enums\EventType;
use App\Models\Event;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminEventIndexFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_filters_events_by_search_type_and_visibility(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $matching = $this->createEvent([
            'title' => 'Posledna stvrt Mesiaca',
            'description' => 'Pozorovanie vecer',
            'type' => EventType::Other->value,
            'visibility' => 1,
        ]);

        $this->createEvent([
            'title' => 'Posledna stvrt Mesiaca - skryta',
            'description' => 'Rovnaky text, ina viditelnost',
            'type' => EventType::Other->value,
            'visibility' => 0,
        ]);

        $this->createEvent([
            'title' => 'Posledna stvrt Mesiaca - iny typ',
            'description' => 'Rovnaky text, iny typ',
            'type' => EventType::MeteorShower->value,
            'visibility' => 1,
        ]);

        $response = $this->getJson('/api/admin/events?search=Mesiaca&type=other&visibility=1');

        $response
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $matching->id);
    }

    public function test_index_accepts_visibility_zero_filter_value(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $hidden = $this->createEvent([
            'title' => 'Skryta udalost',
            'visibility' => 0,
        ]);
        $this->createEvent([
            'title' => 'Verejna udalost',
            'visibility' => 1,
        ]);

        $response = $this->getJson('/api/admin/events?visibility=0');

        $response
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $hidden->id)
            ->assertJsonPath('data.0.visibility', 0);
    }

    public function test_index_filters_events_by_year_month_and_day(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $dec30 = $this->createEvent([
            'title' => 'Dec 30 event',
            'start_at' => CarbonImmutable::create(2026, 12, 30, 19, 59, 0, 'UTC'),
            'max_at' => CarbonImmutable::create(2026, 12, 30, 19, 59, 0, 'UTC'),
        ]);
        $this->createEvent([
            'title' => 'Dec 31 event',
            'start_at' => CarbonImmutable::create(2026, 12, 31, 10, 0, 0, 'UTC'),
            'max_at' => CarbonImmutable::create(2026, 12, 31, 10, 0, 0, 'UTC'),
        ]);
        $this->createEvent([
            'title' => 'Jan 1 event',
            'start_at' => CarbonImmutable::create(2027, 1, 1, 0, 5, 0, 'UTC'),
            'max_at' => CarbonImmutable::create(2027, 1, 1, 0, 5, 0, 'UTC'),
        ]);

        $this->getJson('/api/admin/events?year=2026')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        $this->getJson('/api/admin/events?year=2026&month=12')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        $this->getJson('/api/admin/events?year=2026&month=12&day=30')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $dec30->id);
    }

    private function createEvent(array $overrides = []): Event
    {
        $startAt = now()->utc()->addHour();

        return Event::query()->create(array_merge([
            'title' => 'Test event',
            'type' => EventType::Other->value,
            'start_at' => $startAt,
            'end_at' => null,
            'max_at' => $startAt,
            'short' => null,
            'description' => null,
            'visibility' => 1,
            'source_name' => 'manual',
            'source_uid' => (string) Str::uuid(),
        ], $overrides));
    }
}

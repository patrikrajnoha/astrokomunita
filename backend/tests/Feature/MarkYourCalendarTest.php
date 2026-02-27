<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\MonthlyFeaturedEvent;
use App\Models\User;
use App\Repositories\MarkYourCalendarRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MarkYourCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_admin_mode_when_featured_exists(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-21 10:00:00', 'UTC'));
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $event = $this->createEvent('Admin Event', '2026-02-24 18:00:00');
        MonthlyFeaturedEvent::query()->create([
            'event_id' => $event->id,
            'month_key' => '2026-02',
            'position' => 0,
            'is_active' => true,
        ]);

        $this->getJson('/api/popup/mark-your-calendar')
            ->assertOk()
            ->assertJsonPath('mode', 'admin')
            ->assertJsonCount(1, 'events')
            ->assertJsonPath('events.0.id', $event->id);
    }

    public function test_returns_fallback_mode_when_none_featured(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-21 10:00:00', 'UTC'));
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $first = $this->createEvent('Fallback 1', '2026-02-22 18:00:00');
        $second = $this->createEvent('Fallback 2', '2026-02-23 18:00:00');
        $third = $this->createEvent('Fallback 3', '2026-02-24 18:00:00');
        $this->createEvent('Fallback 4', '2026-02-25 18:00:00');

        $response = $this->getJson('/api/popup/mark-your-calendar');

        $response->assertOk()
            ->assertJsonPath('mode', 'fallback')
            ->assertJsonCount(3, 'events')
            ->assertJsonPath('events.0.id', $first->id)
            ->assertJsonPath('events.1.id', $second->id)
            ->assertJsonPath('events.2.id', $third->id);
    }

    public function test_never_returns_500_when_repository_fails(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-21 10:00:00', 'UTC'));
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->app->instance(MarkYourCalendarRepository::class, new class extends MarkYourCalendarRepository {
            /**
             * @return array<int, Event>
             */
            public function adminFeaturedEvents(string $monthKey, Carbon $now, int $limit): array
            {
                throw new \RuntimeException('Repository failure');
            }
        });

        $this->getJson('/api/popup/mark-your-calendar')
            ->assertOk()
            ->assertJsonPath('mode', 'empty')
            ->assertJsonPath('events', []);
    }

    public function test_response_structure_is_correct(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-21 10:00:00', 'UTC'));
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $event = $this->createEvent('Structured Event', '2026-02-24 18:00:00');
        MonthlyFeaturedEvent::query()->create([
            'event_id' => $event->id,
            'month_key' => '2026-02',
            'position' => 0,
            'is_active' => true,
        ]);

        $this->getJson('/api/popup/mark-your-calendar')
            ->assertOk()
            ->assertJsonStructure([
                'mode',
                'events',
                'events' => [
                    '*' => [
                        'id',
                        'title',
                        'start_at',
                        'end_at',
                        'calendar' => [
                            'google_calendar_url',
                            'ics_url',
                        ],
                    ],
                ],
            ]);
    }

    private function createEvent(string $title, string $startAt): Event
    {
        return Event::query()->create([
            'title' => $title,
            'type' => 'other',
            'start_at' => Carbon::parse($startAt, 'UTC'),
            'end_at' => Carbon::parse($startAt, 'UTC')->addHour(),
            'visibility' => 1,
            'source_name' => 'manual',
            'source_uid' => uniqid('manual-', true),
        ]);
    }
}

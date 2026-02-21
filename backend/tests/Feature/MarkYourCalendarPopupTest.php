<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Event;
use App\Models\MonthlyFeaturedEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MarkYourCalendarPopupTest extends TestCase
{
    use RefreshDatabase;

    public function test_monthly_cadence_shows_when_last_seen_is_previous_month(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-17 10:00:00', 'UTC'));
        $user = User::factory()->create([
            'last_calendar_popup_at' => Carbon::parse('2026-01-31 23:59:00', 'UTC'),
            'calendar_popup_last_force_version' => 0,
        ]);
        Sanctum::actingAs($user);

        $event = $this->createEvent('Alpha');
        MonthlyFeaturedEvent::query()->create([
            'event_id' => $event->id,
            'position' => 0,
            'is_active' => true,
        ]);

        $this->getJson('/api/popup/mark-your-calendar')
            ->assertOk()
            ->assertJsonPath('should_show', true)
            ->assertJsonPath('reason', 'monthly')
            ->assertJsonPath('month_key', '2026-02');
    }

    public function test_monthly_cadence_hides_when_already_seen_this_month(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-17 10:00:00', 'UTC'));
        $user = User::factory()->create([
            'last_calendar_popup_at' => Carbon::parse('2026-02-01 08:00:00', 'UTC'),
            'calendar_popup_last_force_version' => 0,
        ]);
        Sanctum::actingAs($user);

        $event = $this->createEvent('Alpha');
        MonthlyFeaturedEvent::query()->create([
            'event_id' => $event->id,
            'position' => 0,
            'is_active' => true,
        ]);

        $this->getJson('/api/popup/mark-your-calendar')
            ->assertOk()
            ->assertJsonPath('should_show', false)
            ->assertJsonPath('reason', 'already_seen');
    }

    public function test_forced_cadence_shows_once_until_seen_then_waits_for_next_force(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-17 10:00:00', 'UTC'));
        AppSetting::put('calendar_popup_force_version', 3);

        $user = User::factory()->create([
            'last_calendar_popup_at' => Carbon::parse('2026-02-10 08:00:00', 'UTC'),
            'calendar_popup_last_force_version' => 2,
        ]);
        Sanctum::actingAs($user);

        $event = $this->createEvent('Alpha');
        MonthlyFeaturedEvent::query()->create([
            'event_id' => $event->id,
            'position' => 0,
            'is_active' => true,
        ]);

        $this->getJson('/api/popup/mark-your-calendar')
            ->assertOk()
            ->assertJsonPath('should_show', true)
            ->assertJsonPath('reason', 'forced')
            ->assertJsonPath('force_version', 3);

        $this->postJson('/api/popup/mark-your-calendar/seen', [
            'force_version' => 3,
            'month_key' => '2026-02',
        ])->assertOk()
            ->assertJsonPath('ok', true);

        $this->getJson('/api/popup/mark-your-calendar')
            ->assertOk()
            ->assertJsonPath('should_show', false)
            ->assertJsonPath('reason', 'already_seen');
    }

    public function test_payload_includes_only_curated_events_ordered_by_position(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-17 10:00:00', 'UTC'));
        $user = User::factory()->create([
            'last_calendar_popup_at' => null,
            'calendar_popup_last_force_version' => 0,
        ]);
        Sanctum::actingAs($user);

        $eventA = $this->createEvent('Third');
        $eventB = $this->createEvent('First');
        $eventC = $this->createEvent('Second');
        $eventHidden = $this->createEvent('Hidden');

        MonthlyFeaturedEvent::query()->create([
            'event_id' => $eventA->id,
            'position' => 2,
            'is_active' => true,
        ]);
        MonthlyFeaturedEvent::query()->create([
            'event_id' => $eventB->id,
            'position' => 0,
            'is_active' => true,
        ]);
        MonthlyFeaturedEvent::query()->create([
            'event_id' => $eventC->id,
            'position' => 1,
            'is_active' => true,
        ]);
        MonthlyFeaturedEvent::query()->create([
            'event_id' => $eventHidden->id,
            'position' => 3,
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/popup/mark-your-calendar');

        $response->assertOk()
            ->assertJsonPath('selection_mode', 'admin')
            ->assertJsonPath('items.0.id', $eventB->id)
            ->assertJsonPath('items.1.id', $eventC->id)
            ->assertJsonPath('items.2.id', $eventA->id)
            ->assertJsonMissingPath('items.3.id')
            ->assertJsonPath('meta.max_items', 10)
            ->assertJsonPath('meta.max_rows', 2);
    }

    private function createEvent(string $title): Event
    {
        return Event::query()->create([
            'title' => $title,
            'type' => 'other',
            'start_at' => now()->addDays(2),
            'end_at' => now()->addDays(3),
            'visibility' => 1,
            'source_name' => 'manual',
            'source_uid' => uniqid('manual-', true),
        ]);
    }
}

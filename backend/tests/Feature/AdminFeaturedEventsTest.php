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

class AdminFeaturedEventsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_cannot_exceed_six_active_featured_events(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'role' => 'admin',
            'is_active' => true,
        ]);
        Sanctum::actingAs($admin);

        foreach (range(1, 6) as $index) {
            $event = $this->createEvent("Event {$index}");
            MonthlyFeaturedEvent::query()->create([
                'event_id' => $event->id,
                'position' => $index - 1,
                'is_active' => true,
                'created_by' => $admin->id,
            ]);
        }

        $seventh = $this->createEvent('Seventh');

        $this->postJson('/api/admin/featured-events', [
            'event_id' => $seventh->id,
        ])->assertStatus(422)
            ->assertJsonPath('errors.event_id.0', 'Maximum 6 active featured events are allowed.');
    }

    public function test_force_endpoint_increments_global_version(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'role' => 'admin',
            'is_active' => true,
        ]);
        Sanctum::actingAs($admin);

        AppSetting::put('calendar_popup_force_version', 2);
        Carbon::setTestNow(Carbon::parse('2026-02-17 12:00:00', 'UTC'));

        $this->postJson('/api/admin/featured-events/force-popup')
            ->assertOk()
            ->assertJsonPath('force_version', 3)
            ->assertJsonPath('force_at', '2026-02-17T12:00:00+00:00');

        $this->assertSame(3, AppSetting::getInt('calendar_popup_force_version', 0));
        $this->assertSame('2026-02-17T12:00:00+00:00', AppSetting::getString('calendar_popup_force_at'));
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

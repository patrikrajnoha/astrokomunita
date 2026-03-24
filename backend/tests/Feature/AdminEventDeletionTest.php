<?php

namespace Tests\Feature;

use App\Enums\EventType;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminEventDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_delete_single_event(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $event = $this->createEvent([
            'title' => 'Delete me',
            'source_name' => 'manual',
        ]);

        $this->deleteJson('/api/admin/events/'.$event->id)
            ->assertOk()
            ->assertJsonPath('deleted', true)
            ->assertJsonPath('id', $event->id);

        $this->assertDatabaseMissing('events', ['id' => $event->id]);
    }

    public function test_bulk_delete_supports_dry_run_for_filtered_scope(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $manual = $this->createEvent([
            'title' => 'Manual event',
            'source_name' => 'manual',
        ]);
        $crawled = $this->createEvent([
            'title' => 'Crawled event',
            'source_name' => 'astropixels',
        ]);

        $response = $this->postJson('/api/admin/events/bulk-delete', [
            'scope' => 'filtered',
            'dry_run' => true,
            'filters' => [
                'source_kind' => 'manual',
            ],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('status', 'dry_run')
            ->assertJsonPath('matched', 1);

        $this->assertDatabaseHas('events', ['id' => $manual->id]);
        $this->assertDatabaseHas('events', ['id' => $crawled->id]);
    }

    public function test_bulk_delete_can_remove_filtered_crawled_events(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $this->createEvent([
            'title' => 'Manual meteors',
            'type' => EventType::Meteors->value,
            'source_name' => 'manual',
        ]);
        $target = $this->createEvent([
            'title' => 'Crawled meteors',
            'type' => EventType::Meteors->value,
            'source_name' => 'astropixels',
        ]);
        $this->createEvent([
            'title' => 'Crawled eclipse',
            'type' => EventType::Eclipse->value,
            'source_name' => 'astropixels',
        ]);

        $this->postJson('/api/admin/events/bulk-delete', [
            'scope' => 'filtered',
            'confirm_token' => 'delete_events',
            'filters' => [
                'type' => EventType::Meteors->value,
                'source_kind' => 'crawled',
            ],
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('deleted', 1);

        $this->assertDatabaseMissing('events', ['id' => $target->id]);
    }

    public function test_bulk_delete_can_remove_all_events_with_confirm_token(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $this->createEvent(['source_name' => 'manual']);
        $this->createEvent(['source_name' => 'astropixels']);
        $this->createEvent(['source_name' => 'timeanddate']);

        $this->postJson('/api/admin/events/bulk-delete', [
            'scope' => 'all',
            'confirm_token' => 'delete_events',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('deleted', 3);

        $this->assertDatabaseCount('events', 0);
    }

    private function createEvent(array $overrides = []): Event
    {
        $startAt = now()->utc()->addHour();
        $sourceName = (string) ($overrides['source_name'] ?? 'manual');

        return Event::query()->create(array_merge([
            'title' => 'Test event',
            'type' => EventType::Other->value,
            'start_at' => $startAt,
            'end_at' => null,
            'max_at' => $startAt,
            'short' => null,
            'description' => null,
            'visibility' => 1,
            'source_name' => $sourceName,
            'source_uid' => (string) Str::uuid(),
        ], $overrides));
    }
}


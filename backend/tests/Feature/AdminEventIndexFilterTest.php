<?php

namespace Tests\Feature;

use App\Enums\EventType;
use App\Models\EventCandidate;
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

    public function test_index_with_published_scope_excludes_hidden_and_unapproved_events(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $manualVisible = $this->createEvent([
            'title' => 'Manual visible',
            'visibility' => 1,
            'source_name' => 'manual',
            'source_uid' => 'manual-visible',
        ]);

        $this->createEvent([
            'title' => 'Manual hidden',
            'visibility' => 0,
            'source_name' => 'manual',
            'source_uid' => 'manual-hidden',
        ]);

        $approvedCrawled = $this->createEvent([
            'title' => 'Approved crawled',
            'visibility' => 1,
            'source_name' => 'astropixels',
            'source_uid' => 'approved-crawled',
        ]);
        EventCandidate::query()->create([
            'source_name' => 'astropixels',
            'source_uid' => 'approved-crawled',
            'source_hash' => sha1('approved-crawled'),
            'title' => 'Approved candidate',
            'type' => EventType::Other->value,
            'max_at' => now()->utc(),
            'start_at' => now()->utc(),
            'status' => EventCandidate::STATUS_APPROVED,
            'published_event_id' => $approvedCrawled->id,
        ]);

        $this->createEvent([
            'title' => 'Pending crawled',
            'visibility' => 1,
            'source_name' => 'astropixels',
            'source_uid' => 'pending-crawled',
        ]);
        EventCandidate::query()->create([
            'source_name' => 'astropixels',
            'source_uid' => 'pending-crawled',
            'source_hash' => sha1('pending-crawled'),
            'title' => 'Pending candidate',
            'type' => EventType::Other->value,
            'max_at' => now()->utc(),
            'start_at' => now()->utc(),
            'status' => EventCandidate::STATUS_PENDING,
            'published_event_id' => null,
        ]);

        $response = $this->getJson('/api/admin/events?scope=published');

        $response
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$manualVisible->id, $approvedCrawled->id], $ids);
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

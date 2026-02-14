<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EventPersonalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_mine_feed(): void
    {
        $this->getJson('/api/events?feed=mine')
            ->assertStatus(401);
    }

    public function test_user_without_preferences_gets_default_feed_for_mine(): void
    {
        $user = User::factory()->create();

        $first = $this->createPublishedEvent('meteors', 'sk');
        $second = $this->createPublishedEvent('conjunction', 'global');

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/events?feed=mine');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($first->id, $ids);
        $this->assertContains($second->id, $ids);
    }

    public function test_user_with_preferences_gets_only_matching_events(): void
    {
        $user = User::factory()->create();

        $wanted = $this->createPublishedEvent('meteors', 'global');
        $this->createPublishedEvent('meteors', 'sk');
        $this->createPublishedEvent('eclipse', 'eu');

        UserPreference::query()->create([
            'user_id' => $user->id,
            'event_types' => ['meteors'],
            'region' => 'eu',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/events?feed=mine');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertSame([$wanted->id], $ids);
    }

    public function test_preferences_update_validates_payload(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/me/preferences', [
            'event_types' => ['invalid_type'],
            'region' => 'moon',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['event_types.0', 'region']);
    }

    private function createPublishedEvent(string $type, string $region): Event
    {
        return Event::query()->create([
            'title' => sprintf('Event %s %s', $type, uniqid()),
            'type' => $type,
            'region_scope' => $region,
            'start_at' => now()->addDays(2),
            'end_at' => now()->addDays(2)->addHour(),
            'max_at' => now()->addDays(2),
            'visibility' => 1,
            'source_name' => 'manual',
            'source_uid' => uniqid('event_', true),
        ]);
    }
}

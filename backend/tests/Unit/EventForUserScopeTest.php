<?php

namespace Tests\Unit;

use App\Models\Event;
use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventForUserScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_scope_for_user_applies_types_and_region_rules(): void
    {
        $user = User::factory()->create();

        $matchEu = $this->createEvent('meteors', 'eu');
        $matchGlobal = $this->createEvent('meteors', 'global');
        $nonMatchingType = $this->createEvent('eclipse', 'eu');
        $nonMatchingRegion = $this->createEvent('meteors', 'sk');

        UserPreference::query()->create([
            'user_id' => $user->id,
            'event_types' => ['meteors'],
            'region' => 'eu',
        ]);

        $ids = Event::query()
            ->forUser($user)
            ->pluck('id')
            ->all();

        $this->assertContains($matchEu->id, $ids);
        $this->assertContains($matchGlobal->id, $ids);
        $this->assertNotContains($nonMatchingType->id, $ids);
        $this->assertNotContains($nonMatchingRegion->id, $ids);
    }

    public function test_scope_for_user_keeps_query_unfiltered_without_preferences(): void
    {
        $user = User::factory()->create();

        $first = $this->createEvent('meteors', 'sk');
        $second = $this->createEvent('eclipse', 'global');

        $ids = Event::query()
            ->forUser($user)
            ->pluck('id')
            ->all();

        $this->assertContains($first->id, $ids);
        $this->assertContains($second->id, $ids);
    }

    private function createEvent(string $type, string $region): Event
    {
        return Event::query()->create([
            'title' => sprintf('Scope event %s %s', $type, uniqid()),
            'type' => $type,
            'region_scope' => $region,
            'max_at' => now()->addDay(),
            'visibility' => 1,
            'source_name' => 'manual',
            'source_uid' => uniqid('scope_', true),
        ]);
    }
}

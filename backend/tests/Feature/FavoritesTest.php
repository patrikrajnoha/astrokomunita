<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Favorite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FavoritesTest extends TestCase
{
    use RefreshDatabase;

    public function test_favorites_require_auth(): void
    {
        $event = Event::create([
            'title' => 'Test event',
            'type' => 'other',
        ]);

        $this->getJson('/api/favorites')->assertStatus(401);
        $this->postJson('/api/favorites', ['event_id' => $event->id])->assertStatus(401);
        $this->deleteJson("/api/favorites/{$event->id}")->assertStatus(401);
    }

    public function test_favorites_are_scoped_per_user(): void
    {
        $event = Event::create([
            'title' => 'Test event',
            'type' => 'other',
        ]);

        $userA = User::factory()->create();
        $userB = User::factory()->create();

        Favorite::create(['user_id' => $userA->id, 'event_id' => $event->id]);

        Sanctum::actingAs($userB);
        $this->getJson('/api/favorites')
            ->assertOk()
            ->assertJsonCount(0);

        Sanctum::actingAs($userA);
        $this->getJson('/api/favorites')
            ->assertOk()
            ->assertJsonCount(1);
    }
}

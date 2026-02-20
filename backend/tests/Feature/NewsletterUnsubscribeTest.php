<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class NewsletterUnsubscribeTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_unsubscribe_link_turns_off_newsletter_subscription(): void
    {
        $user = User::factory()->create([
            'newsletter_subscribed' => true,
        ]);

        $url = URL::temporarySignedRoute(
            'newsletter.unsubscribe',
            now()->addDays(30),
            ['user' => (int) $user->id, 'run' => 1]
        );

        $this->get($url)
            ->assertOk()
            ->assertSeeText('Odhlasenie prebehlo uspesne');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'newsletter_subscribed' => false,
        ]);
    }

    public function test_unsubscribe_route_rejects_invalid_signature(): void
    {
        $user = User::factory()->create([
            'newsletter_subscribed' => true,
        ]);

        $this->get('/unsubscribe/newsletter/' . $user->id . '?run=1')
            ->assertStatus(403);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'newsletter_subscribed' => true,
        ]);
    }
}


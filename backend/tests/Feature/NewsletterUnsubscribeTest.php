<?php

namespace Tests\Feature;

use App\Models\NewsletterRun;
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

        $run = NewsletterRun::query()->create([
            'week_start_date' => '2026-02-23',
            'status' => NewsletterRun::STATUS_COMPLETED,
            'total_recipients' => 1,
            'sent_count' => 1,
            'preview_count' => 0,
            'unsubscribe_count' => 0,
            'failed_count' => 0,
            'forced' => false,
            'dry_run' => false,
            'error' => null,
        ]);

        $url = URL::temporarySignedRoute(
            'newsletter.unsubscribe',
            now()->addDays(30),
            ['user' => (int) $user->id, 'run' => (int) $run->id]
        );

        $this->get($url)
            ->assertOk()
            ->assertSeeText('Odhlásenie prebehlo úspešne');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'newsletter_subscribed' => false,
        ]);

        $this->assertDatabaseHas('newsletter_runs', [
            'id' => (int) $run->id,
            'unsubscribe_count' => 1,
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

    public function test_unsubscribe_counter_is_not_incremented_when_user_is_already_unsubscribed(): void
    {
        $user = User::factory()->create([
            'newsletter_subscribed' => false,
        ]);

        $run = NewsletterRun::query()->create([
            'week_start_date' => '2026-02-23',
            'status' => NewsletterRun::STATUS_COMPLETED,
            'total_recipients' => 1,
            'sent_count' => 1,
            'preview_count' => 0,
            'unsubscribe_count' => 0,
            'failed_count' => 0,
            'forced' => false,
            'dry_run' => false,
            'error' => null,
        ]);

        $url = URL::temporarySignedRoute(
            'newsletter.unsubscribe',
            now()->addDays(30),
            ['user' => (int) $user->id, 'run' => (int) $run->id]
        );

        $this->get($url)->assertOk();

        $this->assertDatabaseHas('newsletter_runs', [
            'id' => (int) $run->id,
            'unsubscribe_count' => 0,
        ]);
    }
}

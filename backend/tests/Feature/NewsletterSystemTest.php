<?php

namespace Tests\Feature;

use App\Jobs\SendNewsletterToUserJob;
use App\Mail\WeeklyNewsletterMail;
use App\Models\BlogPost;
use App\Models\Event;
use App\Models\NewsletterRun;
use App\Models\User;
use App\Services\Newsletter\NewsletterDispatchService;
use App\Services\Newsletter\NewsletterSelectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NewsletterSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-02-19 10:00:00', 'UTC'));
        config()->set('newsletter.frontend_base_url', 'https://astro.test');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_subscribed_users_receive_newsletter(): void
    {
        Mail::fake();
        $this->seedNewsletterContent();

        $subscribed = User::factory()->create([
            'newsletter_subscribed' => true,
            'is_active' => true,
            'is_bot' => false,
        ]);

        app(NewsletterDispatchService::class)->dispatchWeeklyNewsletter();

        Mail::assertSent(WeeklyNewsletterMail::class, function (WeeklyNewsletterMail $mail) use ($subscribed): bool {
            return $mail->hasTo($subscribed->email);
        });

        $run = NewsletterRun::query()
            ->whereDate('week_start_date', '2026-02-23')
            ->firstOrFail();

        $this->assertSame(NewsletterRun::STATUS_COMPLETED, $run->status);
        $this->assertSame(1, $run->total_recipients);
        $this->assertSame(1, $run->sent_count);
        $this->assertSame(0, $run->failed_count);
        $this->assertNotNull($run->started_at);
        $this->assertNotNull($run->finished_at);
        $this->assertNull($run->error);
    }

    public function test_unsubscribed_users_do_not_receive_newsletter(): void
    {
        Mail::fake();
        $this->seedNewsletterContent();

        $subscribed = User::factory()->create([
            'newsletter_subscribed' => true,
        ]);
        $unsubscribed = User::factory()->create([
            'newsletter_subscribed' => false,
        ]);

        app(NewsletterDispatchService::class)->dispatchWeeklyNewsletter();

        Mail::assertSent(WeeklyNewsletterMail::class, function (WeeklyNewsletterMail $mail) use ($subscribed): bool {
            return $mail->hasTo($subscribed->email);
        });
        Mail::assertNotSent(WeeklyNewsletterMail::class, function (WeeklyNewsletterMail $mail) use ($unsubscribed): bool {
            return $mail->hasTo($unsubscribed->email);
        });
    }

    public function test_idempotency_running_twice_does_not_double_send(): void
    {
        Mail::fake();
        $this->seedNewsletterContent();

        User::factory()->create([
            'newsletter_subscribed' => true,
        ]);

        Artisan::call('newsletter:send-weekly');
        Artisan::call('newsletter:send-weekly');

        Mail::assertSent(WeeklyNewsletterMail::class, 1);
        $this->assertSame(1, NewsletterRun::query()->where('dry_run', false)->count());
    }

    public function test_admin_manual_send_creates_run(): void
    {
        Mail::fake();
        $this->seedNewsletterContent();

        User::factory()->create([
            'newsletter_subscribed' => true,
        ]);
        $admin = User::factory()->create([
            'is_admin' => true,
            'role' => 'admin',
            'is_active' => true,
        ]);

        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/newsletter/send', [])
            ->assertStatus(202)
            ->assertJsonPath('created', true);

        $this->assertTrue(
            NewsletterRun::query()
                ->where('admin_user_id', $admin->id)
                ->whereDate('week_start_date', '2026-02-23')
                ->exists()
        );
    }

    public function test_admin_preview_endpoint_sends_preview_mail_with_subject_prefix(): void
    {
        Mail::fake();
        $this->seedNewsletterContent();

        $target = User::factory()->create([
            'newsletter_subscribed' => false,
            'is_active' => true,
            'is_bot' => false,
        ]);

        $admin = User::factory()->create([
            'is_admin' => true,
            'role' => 'admin',
            'is_active' => true,
        ]);

        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/newsletter/preview', [
            'email' => $target->email,
        ])
            ->assertStatus(202)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.email', $target->email);

        Mail::assertSent(WeeklyNewsletterMail::class, function (WeeklyNewsletterMail $mail) use ($target): bool {
            return $mail->hasTo($target->email)
                && str_starts_with((string) $mail->envelope()->subject, '[PREVIEW] ');
        });
    }

    public function test_dry_run_does_not_send_mail(): void
    {
        Mail::fake();
        $this->seedNewsletterContent();

        User::factory()->create([
            'newsletter_subscribed' => true,
        ]);
        $admin = User::factory()->create([
            'is_admin' => true,
            'role' => 'admin',
            'is_active' => true,
        ]);
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/newsletter/send', [
            'dry_run' => true,
        ])->assertStatus(202)
            ->assertJsonPath('created', true);

        Mail::assertNothingSent();
        $this->assertDatabaseHas('newsletter_runs', [
            'dry_run' => true,
            'status' => NewsletterRun::STATUS_COMPLETED,
            'sent_count' => 1,
            'error' => null,
        ]);
    }

    public function test_weekly_scheduler_registers_newsletter_command(): void
    {
        Artisan::call('schedule:list');
        $output = Artisan::output();

        $this->assertStringContainsString('newsletter:send-weekly', $output);
        $this->assertMatchesRegularExpression('/0\s+8\s+\*\s+\*\s+1/', $output);
    }

    public function test_run_counters_increment_properly_when_some_recipients_fail(): void
    {
        Mail::fake();
        $this->seedNewsletterContent();

        $user = User::factory()->create([
            'newsletter_subscribed' => true,
            'is_active' => true,
            'is_bot' => false,
        ]);

        $run = NewsletterRun::query()->create([
            'week_start_date' => '2026-02-23',
            'status' => NewsletterRun::STATUS_RUNNING,
            'total_recipients' => 2,
            'sent_count' => 0,
            'failed_count' => 0,
            'forced' => false,
            'dry_run' => false,
        ]);

        $payload = app(NewsletterSelectionService::class)->buildNewsletterPayload();
        $payload['run'] = [
            'id' => $run->id,
            'week_start_date' => '2026-02-23',
            'forced' => false,
            'dry_run' => false,
        ];

        $job = new SendNewsletterToUserJob(
            runId: (int) $run->id,
            userIds: [(int) $user->id, 999999],
            payload: $payload,
            dryRun: false
        );

        $job->handle(app(NewsletterDispatchService::class));

        $run->refresh();
        $this->assertSame(1, $run->sent_count);
        $this->assertSame(1, $run->failed_count);
        $this->assertSame(NewsletterRun::STATUS_COMPLETED, $run->status);
        Mail::assertSent(WeeklyNewsletterMail::class, 1);
    }

    public function test_user_can_toggle_newsletter_subscription(): void
    {
        $user = User::factory()->create([
            'newsletter_subscribed' => false,
        ]);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/me/newsletter', [
                'newsletter_subscribed' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.newsletter_subscribed', true);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'newsletter_subscribed' => true,
        ]);
    }

    private function seedNewsletterContent(): void
    {
        $event = $this->createNextWeekEvent('Meteor shower peak');
        $this->createArticle('weekly-sky-tip');

        app(NewsletterSelectionService::class)->replaceAdminSelectedEvents([$event->id]);
    }

    private function createNextWeekEvent(string $title): Event
    {
        $start = Carbon::now()
            ->startOfWeek(Carbon::MONDAY)
            ->addWeek()
            ->addDays(1)
            ->setTime(20, 0, 0);

        return Event::query()->create([
            'title' => $title,
            'type' => 'other',
            'start_at' => $start,
            'end_at' => $start->copy()->addHour(),
            'visibility' => 1,
            'source_name' => 'manual',
            'source_uid' => uniqid('newsletter-', true),
        ]);
    }

    private function createArticle(string $slug): BlogPost
    {
        $author = User::factory()->create();

        return BlogPost::query()->create([
            'user_id' => $author->id,
            'title' => 'Weekly astronomy guide',
            'slug' => $slug,
            'content' => 'Article body',
            'published_at' => Carbon::now()->subDays(2),
            'views' => 42,
        ]);
    }
}

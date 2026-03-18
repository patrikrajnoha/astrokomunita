<?php

namespace Tests\Feature;

use App\Jobs\SendNewsletterToUserJob;
use App\Mail\WeeklyNewsletterMail;
use App\Models\BlogPost;
use App\Models\Event;
use App\Models\NewsletterRun;
use App\Models\User;
use App\Services\Events\EventInsightsCacheService;
use App\Services\Newsletter\NewsletterDispatchService;
use App\Services\Newsletter\NewsletterSelectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
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

    public function test_dispatch_respects_configured_batch_size_when_queueing_jobs(): void
    {
        Queue::fake();
        $this->seedNewsletterContent();

        User::factory()->count(3)->create([
            'newsletter_subscribed' => true,
            'is_active' => true,
            'is_bot' => false,
        ]);

        config()->set('newsletter.batch_size', 2);
        config()->set('newsletter.max_recipients_per_run', 0);

        app(NewsletterDispatchService::class)->dispatchWeeklyNewsletter();

        Queue::assertPushed(SendNewsletterToUserJob::class, 2);
        Queue::assertPushed(SendNewsletterToUserJob::class, function (SendNewsletterToUserJob $job): bool {
            return count($job->userIds) === 2;
        });
        Queue::assertPushed(SendNewsletterToUserJob::class, function (SendNewsletterToUserJob $job): bool {
            return count($job->userIds) === 1;
        });
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
            ->assertJsonPath('data.email', $target->email)
            ->assertJsonPath('data.preview_count', 1);

        Mail::assertSent(WeeklyNewsletterMail::class, function (WeeklyNewsletterMail $mail) use ($target): bool {
            return $mail->hasTo($target->email)
                && (string) $mail->envelope()->subject === '[PREVIEW] Nebesky sprievodca: Tyzdenny newsletter';
        });

        $this->assertTrue(
            NewsletterRun::query()
                ->whereDate('week_start_date', '2026-02-23')
                ->where('status', NewsletterRun::STATUS_COMPLETED)
                ->where('dry_run', true)
                ->where('total_recipients', 0)
                ->where('preview_count', 1)
                ->exists()
        );
    }

    public function test_admin_preview_endpoint_applies_copy_overrides_only_for_preview_render(): void
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

        $subjectOverride = 'AI subject pre preview';
        $introOverride = 'AI intro bez technickych detailov.';
        $tipOverride = 'AI tip: vyber tmavsiu lokalitu a nechaj oci adaptovat sa na tmu.';

        $this->postJson('/api/admin/newsletter/preview', [
            'email' => $target->email,
            'subject_override' => $subjectOverride,
            'intro_override' => $introOverride,
            'tip_override' => $tipOverride,
        ])
            ->assertStatus(202)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.email', $target->email);

        Mail::assertSent(WeeklyNewsletterMail::class, function (WeeklyNewsletterMail $mail) use ($target, $subjectOverride, $introOverride, $tipOverride): bool {
            return $mail->hasTo($target->email)
                && (string) $mail->envelope()->subject === '[PREVIEW] ' . $subjectOverride
                && (string) data_get($mail->payload, 'intro_override') === $introOverride
                && (string) data_get($mail->payload, 'astronomical_tip') === $tipOverride;
        });
    }

    public function test_admin_preview_endpoint_empty_tip_override_falls_back_to_original_tip(): void
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

        $expectedTip = (string) data_get(
            app(NewsletterSelectionService::class)->buildNewsletterPayload(adminPreview: true),
            'astronomical_tip',
            ''
        );
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/newsletter/preview', [
            'email' => $target->email,
            'tip_override' => " \n\t ",
        ])
            ->assertStatus(202)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('warnings.0', 'tip_override ignored: empty after sanitization, fallback applied.');

        Mail::assertSent(WeeklyNewsletterMail::class, function (WeeklyNewsletterMail $mail) use ($target, $expectedTip): bool {
            return $mail->hasTo($target->email)
                && (string) data_get($mail->payload, 'astronomical_tip') === $expectedTip;
        });
    }

    public function test_admin_preview_endpoint_truncates_too_long_subject_override(): void
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
            'subject_override' => str_repeat('A', 100),
        ])
            ->assertStatus(202)
            ->assertJsonPath('ok', true);

        Mail::assertSent(WeeklyNewsletterMail::class, function (WeeklyNewsletterMail $mail) use ($target): bool {
            return $mail->hasTo($target->email)
                && (string) $mail->envelope()->subject === '[PREVIEW] ' . str_repeat('A', 80);
        });
    }

    public function test_admin_preview_endpoint_timestamp_only_override_returns_warning_and_fallback(): void
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

        $expectedTip = (string) data_get(
            app(NewsletterSelectionService::class)->buildNewsletterPayload(adminPreview: true),
            'astronomical_tip',
            ''
        );
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/newsletter/preview', [
            'email' => $target->email,
            'tip_override' => '2026-03-10T11:32:00+00:00',
        ])
            ->assertStatus(202)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('warnings.0', 'tip_override ignored: ISO timestamp-only value is not allowed, fallback applied.');

        Mail::assertSent(WeeklyNewsletterMail::class, function (WeeklyNewsletterMail $mail) use ($target, $expectedTip): bool {
            return $mail->hasTo($target->email)
                && (string) data_get($mail->payload, 'astronomical_tip') === $expectedTip;
        });
    }

    public function test_admin_preview_endpoint_allows_timestamp_inside_normal_sentence(): void
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

        $tipOverride = 'Pozoruj odhad maxima okolo 2026-03-10T11:32:00+00:00 a priprav sa skor.';

        $this->postJson('/api/admin/newsletter/preview', [
            'email' => $target->email,
            'tip_override' => $tipOverride,
        ])
            ->assertStatus(202)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('warnings', []);

        Mail::assertSent(WeeklyNewsletterMail::class, function (WeeklyNewsletterMail $mail) use ($target, $tipOverride): bool {
            return $mail->hasTo($target->email)
                && (string) data_get($mail->payload, 'astronomical_tip') === $tipOverride;
        });
    }

    public function test_production_newsletter_send_ignores_preview_subject_override(): void
    {
        Mail::fake();
        $this->seedNewsletterContent();

        $previewTarget = User::factory()->create([
            'newsletter_subscribed' => false,
            'is_active' => true,
            'is_bot' => false,
        ]);
        $productionRecipient = User::factory()->create([
            'newsletter_subscribed' => true,
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
            'email' => $previewTarget->email,
            'subject_override' => 'Preview-only subject override',
        ])
            ->assertStatus(202)
            ->assertJsonPath('ok', true);

        app(NewsletterDispatchService::class)->dispatchWeeklyNewsletter();

        Mail::assertSent(WeeklyNewsletterMail::class, function (WeeklyNewsletterMail $mail) use ($productionRecipient): bool {
            return $mail->hasTo($productionRecipient->email)
                && $mail->preview === false
                && (string) $mail->envelope()->subject === 'Nebesky sprievodca: Tyzdenny newsletter';
        });
    }

    public function test_admin_preview_endpoint_requires_existing_user_email(): void
    {
        $this->seedNewsletterContent();

        $admin = User::factory()->create([
            'is_admin' => true,
            'role' => 'admin',
            'is_active' => true,
        ]);

        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/newsletter/preview', [
            'email' => 'missing-user@example.com',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_admin_preview_tip_uses_cached_insights_when_available(): void
    {
        $event = $this->createNextWeekEvent('Mesiac v perigeu');
        $this->createArticle('insight-tip-article');
        app(NewsletterSelectionService::class)->replaceAdminSelectedEvents([$event->id]);

        config()->set('events.ai.humanized_pilot_enabled', true);
        $insightsCache = app(EventInsightsCacheService::class);
        Cache::put('events:description_insights:' . $event->id, [
            'why_interesting' => 'Ukazuje zmenu zdanlivej velkosti Mesiaca pri obehu.',
            'how_to_observe' => 'Pozorujte z tmavsieho miesta, pockajte 20 minut na adaptaciu a vyhnite sa presnemu casu 2026-03-10T11:32:00+00:00 UTC.',
            'factual_hash' => $insightsCache->buildEventHash($event),
        ], now()->addDay());

        $admin = User::factory()->create([
            'is_admin' => true,
            'role' => 'admin',
            'is_active' => true,
        ]);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/newsletter/preview')
            ->assertOk();

        $tip = (string) data_get($response->json(), 'data.astronomical_tip', '');
        $this->assertStringContainsString('Pozorujte z tmavsieho miesta', $tip);
        $this->assertStringContainsString('20 minut', $tip);
        $this->assertStringContainsString('Prečo je to zaujímavé', $tip);
        $this->assertStringNotContainsString('2026-03-10T11:32:00+00:00', $tip);
        $this->assertStringNotContainsString('UTC', $tip);
        $this->assertStringNotContainsString('+00:00', $tip);
    }

    public function test_admin_preview_tip_falls_back_when_insights_missing(): void
    {
        $event = $this->createNextWeekEvent('Meteor shower peak');
        $this->createArticle('fallback-tip-article');
        app(NewsletterSelectionService::class)->replaceAdminSelectedEvents([$event->id]);

        config()->set('events.ai.humanized_pilot_enabled', true);
        Cache::forget('events:description_insights:' . $event->id);

        $admin = User::factory()->create([
            'is_admin' => true,
            'role' => 'admin',
            'is_active' => true,
        ]);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/newsletter/preview')
            ->assertOk();

        $tip = (string) data_get($response->json(), 'data.astronomical_tip', '');
        $this->assertStringContainsString('vyhľadajte tmavšie miesto mimo mesta', $tip);
    }

    public function test_admin_preview_tip_ignores_stale_insights_after_event_change(): void
    {
        $event = $this->createNextWeekEvent('Mesiac v perigeu');
        $this->createArticle('stale-insight-article');
        app(NewsletterSelectionService::class)->replaceAdminSelectedEvents([$event->id]);

        config()->set('events.ai.humanized_pilot_enabled', true);
        app(EventInsightsCacheService::class)->put(
            event: $event,
            whyInteresting: 'Pomaha porovnat jas Mesiaca.',
            howToObserve: 'Pozorujte z tmavej lokality.'
        );

        $event->update([
            'title' => 'Mesiac v apogeu',
        ]);

        $this->assertNull(Cache::get('events:description_insights:' . $event->id));

        $admin = User::factory()->create([
            'is_admin' => true,
            'role' => 'admin',
            'is_active' => true,
        ]);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/newsletter/preview')
            ->assertOk();

        $tip = (string) data_get($response->json(), 'data.astronomical_tip', '');
        $this->assertStringContainsString('vyhľadajte tmavšie miesto mimo mesta', $tip);
        $this->assertStringNotContainsString('Pomaha porovnat jas Mesiaca', $tip);
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

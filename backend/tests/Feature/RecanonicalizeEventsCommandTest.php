<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RecanonicalizeEventsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_merges_duplicate_events_and_rebinds_relations(): void
    {
        $user = User::factory()->create();
        $email = 'alerts@example.test';

        $first = Event::query()->create([
            'title' => 'Meteorický roj Geminid',
            'description' => 'First source row.',
            'type' => 'meteor_shower',
            'start_at' => CarbonImmutable::parse('2026-12-14 14:00:00', 'UTC'),
            'max_at' => CarbonImmutable::parse('2026-12-14 14:00:00', 'UTC'),
            'short' => 'First short',
            'visibility' => 1,
            'source_name' => 'astropixels',
            'source_uid' => 'astropixels:geminids:2026',
            'source_hash' => hash('sha256', 'astropixels:geminids:2026'),
            'canonical_key' => 'meteor shower|2026-12-14|meteoricky roj geminid',
            'matched_sources' => ['astropixels'],
            'confidence_score' => 0.70,
        ]);

        $second = Event::query()->create([
            'title' => 'Geminidy (GEM)',
            'description' => 'Second source row.',
            'type' => 'meteor_shower',
            'start_at' => CarbonImmutable::parse('2026-12-14 01:00:00', 'UTC'),
            'max_at' => CarbonImmutable::parse('2026-12-14 01:00:00', 'UTC'),
            'short' => 'Second short',
            'visibility' => 1,
            'source_name' => 'imo',
            'source_uid' => 'imo:geminids:2026',
            'source_hash' => hash('sha256', 'imo:geminids:2026'),
            'canonical_key' => 'meteor shower|2026-12-14|geminidy gem',
            'matched_sources' => ['imo'],
            'confidence_score' => 0.70,
        ]);

        DB::table('user_event_follows')->insert([
            [
                'user_id' => $user->id,
                'event_id' => $first->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $user->id,
                'event_id' => $second->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('event_email_alerts')->insert([
            ['event_id' => $first->id, 'email' => $email, 'created_at' => now()],
            ['event_id' => $second->id, 'email' => $email, 'created_at' => now()],
        ]);

        DB::table('event_reminders')->insert([
            [
                'user_id' => $user->id,
                'event_id' => $first->id,
                'minutes_before' => 60,
                'remind_at' => CarbonImmutable::parse('2026-12-14 13:00:00', 'UTC'),
                'status' => 'pending',
                'sent_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $user->id,
                'event_id' => $second->id,
                'minutes_before' => 60,
                'remind_at' => CarbonImmutable::parse('2026-12-14 00:00:00', 'UTC'),
                'status' => 'pending',
                'sent_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->artisan('events:recanonicalize', [
            '--merge-duplicates' => true,
        ])->assertExitCode(0);

        $this->assertSame(1, Event::query()->count());
        $kept = Event::query()->firstOrFail();

        $this->assertSame('meteor shower|2026-12-14|geminids', $kept->canonical_key);
        $this->assertSame(['astropixels', 'imo'], $kept->matched_sources);
        $this->assertSame('1.00', (string) $kept->confidence_score);

        $this->assertDatabaseCount('user_event_follows', 1);
        $this->assertDatabaseHas('user_event_follows', [
            'user_id' => $user->id,
            'event_id' => $kept->id,
        ]);

        $this->assertDatabaseCount('event_email_alerts', 1);
        $this->assertDatabaseHas('event_email_alerts', [
            'event_id' => $kept->id,
            'email' => $email,
        ]);

        $this->assertDatabaseCount('event_reminders', 1);
        $this->assertDatabaseHas('event_reminders', [
            'user_id' => $user->id,
            'event_id' => $kept->id,
            'minutes_before' => 60,
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventEmailAlertDisabledTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_alert_endpoint_returns_gone_and_does_not_create_alert_record(): void
    {
        $event = Event::query()->create([
            'title' => 'Lunar eclipse',
            'type' => 'eclipse_lunar',
        ]);

        $response = $this->postJson("/api/events/{$event->id}/notify-email", [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(410);
        $response->assertJsonPath('status', 'disabled');
        $response->assertJsonPath('message', 'E-mailove upozornenia su momentalne vypnute.');

        $this->assertDatabaseCount('event_email_alerts', 0);
    }
}

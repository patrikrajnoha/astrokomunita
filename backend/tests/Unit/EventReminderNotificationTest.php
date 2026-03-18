<?php

namespace Tests\Unit;

use App\Models\Event;
use App\Models\EventReminder;
use App\Notifications\EventReminderNotification;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class EventReminderNotificationTest extends TestCase
{
    public function test_mail_uses_events_display_timezone(): void
    {
        config([
            'app.timezone' => 'UTC',
            'events.timezone' => 'Europe/Bratislava',
            'app.url' => 'https://astro.test',
        ]);

        $event = (new Event())->forceFill([
            'id' => 42,
            'title' => 'Letny peak',
            'start_at' => CarbonImmutable::parse('2026-07-06 18:00:00', 'UTC'),
            'max_at' => CarbonImmutable::parse('2026-07-06 18:00:00', 'UTC'),
        ]);

        $reminder = new EventReminder();
        $reminder->setRelation('event', $event);

        $mail = (new EventReminderNotification($reminder))->toMail(new \stdClass());

        $this->assertSame('Upozornenie na udalosť', $mail->subject);
        $this->assertContains('Začiatok: 06.07.2026 20:00', $mail->introLines);
        $this->assertSame('https://astro.test/events/42', $mail->actionUrl);
    }
}

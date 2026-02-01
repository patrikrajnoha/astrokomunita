<?php

namespace App\Notifications;

use App\Models\EventReminder;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EventReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly EventReminder $reminder)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $event = $this->reminder->event;
        $start = $event?->start_at ?? $event?->max_at;
        $startLabel = $start
            ? CarbonImmutable::parse($start)
                ->timezone(config('app.timezone'))
                ->format('d.m.Y H:i')
            : '—';

        return (new MailMessage)
            ->subject('Upozornenie na udalosť')
            ->line('Blíži sa udalosť, ktorú sleduješ.')
            ->line('Udalosť: ' . ($event?->title ?? '—'))
            ->line('Začiatok: ' . $startLabel)
            ->action('Zobraziť detail', $this->eventUrl($event?->id));
    }

    private function eventUrl(?int $id): string
    {
        if (!$id) {
            return rtrim(config('app.url'), '/');
        }
        return rtrim(config('app.url'), '/') . "/events/{$id}";
    }
}

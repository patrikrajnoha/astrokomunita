<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class WeeklyNewsletterMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly array $payload,
        public readonly User $user,
        public readonly bool $preview = false,
    ) {
    }

    public function envelope(): Envelope
    {
        $subject = 'Nebesky sprievodca: Tyzdenny newsletter';
        if ($this->preview) {
            $subject = '[PREVIEW] ' . $subject;
        }

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        $unsubscribeUrl = URL::temporarySignedRoute(
            'newsletter.unsubscribe',
            now()->addDays(max(1, (int) config('newsletter.unsubscribe_url_ttl_days', 30))),
            [
                'user' => (int) $this->user->id,
                'run' => (int) data_get($this->payload, 'run.id', 0),
            ]
        );

        return new Content(
            view: 'emails.newsletter.weekly',
            with: [
                'payload' => $this->payload,
                'recipient' => $this->user,
                'unsubscribeUrl' => $unsubscribeUrl,
            ],
        );
    }
}

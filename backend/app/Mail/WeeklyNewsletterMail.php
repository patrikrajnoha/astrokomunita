<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WeeklyNewsletterMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly array $payload,
        public readonly User $user,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nebesky sprievodca: Tyzdenny newsletter',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.newsletter.weekly',
            with: [
                'payload' => $this->payload,
                'recipient' => $this->user,
            ],
        );
    }
}

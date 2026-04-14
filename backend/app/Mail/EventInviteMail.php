<?php

namespace App\Mail;

use App\Models\EventInvite;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EventInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly EventInvite $invite,
    ) {
    }

    public function envelope(): Envelope
    {
        $from = new Address(
            (string) (config('mail.verification_from.address') ?: config('mail.from.address', 'hello@example.com')),
            (string) config('mail.verification_from.name', config('app.name', 'Astrokomunita'))
        );

        $eventTitle = (string) optional($this->invite->event)->title;
        $subject = $eventTitle
            ? "Pozvánka na podujatie: {$eventTitle}"
            : 'Pozvánka na astronomické podujatie';

        return new Envelope(
            from: $from,
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invites.event_invite',
            with: [
                'invite' => $this->invite,
            ],
        );
    }
}

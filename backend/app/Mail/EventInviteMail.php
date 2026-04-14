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

        $eventTitle = $this->normalizedEventTitle();
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
                'event_title' => $this->normalizedEventTitle(),
            ],
        );
    }

    private function normalizedEventTitle(): string
    {
        return $this->repairUtf8Mojibake((string) optional($this->invite->event)->title);
    }

    private function repairUtf8Mojibake(string $value): string
    {
        if (!function_exists('iconv')) {
            return trim($value);
        }

        // Repair double-encoding: bytes of a UTF-8 char were mis-read as Latin-1
        // and then re-encoded as UTF-8, producing e.g. "Ã½" instead of "ý".
        // Fix: UTF-8 → Latin-1 recovers the original byte sequence, which IS
        // valid UTF-8. Apply up to two rounds to handle triple-encoded inputs.
        $current = trim($value);
        for ($i = 0; $i < 2; $i++) {
            if (!preg_match('/[\x{00C2}\x{00C3}\x{00C4}\x{00C5}]/u', $current)) {
                break;
            }
            $bytes = @iconv('UTF-8', 'ISO-8859-1//IGNORE', $current);
            if (!is_string($bytes) || $bytes === '' || !mb_check_encoding($bytes, 'UTF-8')) {
                break;
            }
            $current = $bytes;
        }

        return $current;
    }
}

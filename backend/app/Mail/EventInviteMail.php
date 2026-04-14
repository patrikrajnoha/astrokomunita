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
        $input = trim($value);
        if ($input === '') {
            return '';
        }

        // Detect double-encoded UTF-8 by looking for U+00C2/C3/C4/C5 code points.
        if (!preg_match('/[\x{00C2}\x{00C3}\x{00C4}\x{00C5}]/u', $input)) {
            return $input;
        }

        // Collapse triple-encoded sequences (byte patterns C3 83 C6 92 C3 82/84/85).
        $collapsed = str_replace(
            ["\xC3\x83\xC6\x92\xC3\x82", "\xC3\x83\xC6\x92\xC3\x84", "\xC3\x83\xC6\x92\xC3\x85"],
            ["\xC3\x83", "\xC3\x84", "\xC3\x85"],
            $input
        );

        if (!function_exists('iconv')) {
            return $collapsed;
        }

        $asLatin1 = @iconv('UTF-8', 'ISO-8859-1//IGNORE', $collapsed);
        if (!is_string($asLatin1) || $asLatin1 === '') {
            return $collapsed;
        }

        $repaired = @mb_convert_encoding($asLatin1, 'UTF-8', 'ISO-8859-1');
        if (!is_string($repaired) || $repaired === '') {
            return $collapsed;
        }

        return preg_match('/[\x{00C2}\x{00C3}\x{00C4}\x{00C5}]/u', $repaired) ? $collapsed : $repaired;
    }
}

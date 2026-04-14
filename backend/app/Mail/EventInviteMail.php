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

        if (!preg_match('/[ÂÃÄÅ]/u', $input)) {
            return $input;
        }

        $collapsed = str_replace(
            ['ÃƒÂ', 'ÃƒÄ', 'ÃƒÅ'],
            ['Ã', 'Ä', 'Å'],
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

        return preg_match('/[ÂÃÄÅ]/u', $repaired) ? $collapsed : $repaired;
    }
}

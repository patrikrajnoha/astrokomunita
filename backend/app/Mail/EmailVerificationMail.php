<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EmailVerificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $code,
        public readonly string $purpose,
    ) {
    }

    public function envelope(): Envelope
    {
        $from = new Address(
            (string) config('mail.verification_from.address', 'noreply@example.com'),
            (string) config('mail.verification_from.name', config('app.name', 'Astrokomunita'))
        );

        return new Envelope(
            from: $from,
            subject: $this->subjectForPurpose($this->purpose),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.auth.email_verification',
            with: [
                'code' => $this->code,
                'purpose' => $this->purpose,
            ],
        );
    }

    private function subjectForPurpose(string $purpose): string
    {
        return match ($purpose) {
            'email_change_current' => 'Potvrdenie zmeny e-mailu',
            default => 'Overenie e-mailovej adresy',
        };
    }
}

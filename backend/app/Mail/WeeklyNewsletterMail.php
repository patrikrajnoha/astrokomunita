<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

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
            $subjectOverride = $this->sanitizeSubjectOverride((string) data_get($this->payload, 'subject_override', ''));
            if ($subjectOverride !== '') {
                $subject = $subjectOverride;
            }

            $subject = '[PREVIEW] ' . $subject;
        }

        return new Envelope(
            subject: $subject,
        );
    }

    private function sanitizeSubjectOverride(string $value): string
    {
        $plain = trim(strip_tags($value));
        if ($plain === '') {
            return '';
        }

        $plain = preg_replace('/\s+/u', ' ', $plain) ?? $plain;

        return Str::limit(trim($plain), 80, '');
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

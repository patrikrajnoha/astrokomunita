<?php

namespace App\Console\Commands;

use App\Services\Newsletter\NewsletterDispatchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\RateLimiter;

class SendWeeklyNewsletterCommand extends Command
{
    protected $signature = 'newsletter:send-weekly
                            {--force : Force create a new run even when a completed run exists}
                            {--dry-run : Build and log run without sending real emails}';

    protected $description = 'Dispatch the weekly newsletter to subscribed users via queue.';

    public function __construct(
        private readonly NewsletterDispatchService $dispatchService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $rateKey = 'newsletter:command:' . now()->format('YmdH');
        $maxAttempts = 3;

        if (RateLimiter::tooManyAttempts($rateKey, $maxAttempts)) {
            $this->warn('newsletter:send-weekly is rate-limited for this hour.');
            return self::FAILURE;
        }

        RateLimiter::hit($rateKey, 3600);

        $result = $this->dispatchService->dispatchWeeklyNewsletter(
            adminUser: null,
            forced: (bool) $this->option('force'),
            dryRun: (bool) $this->option('dry-run')
        );

        $run = $result['run'];
        $reason = (string) ($result['reason'] ?? 'unknown');

        if (! $result['created']) {
            $this->info(sprintf(
                'Newsletter run skipped (%s). Existing run id: %s.',
                $reason,
                $run?->id ? (string) $run->id : '-'
            ));
            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Newsletter run created (id=%d, reason=%s, recipients=%d, dry_run=%s).',
            (int) $run->id,
            $reason,
            (int) $run->total_recipients,
            $run->dry_run ? 'true' : 'false'
        ));

        return self::SUCCESS;
    }
}

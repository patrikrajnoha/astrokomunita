<?php

namespace App\Console\Commands;

use App\Models\EventReminder;
use App\Notifications\EventReminderNotification;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendEventReminders extends Command
{
    protected $signature = 'reminders:send';

    protected $description = 'Send pending event reminders via email.';

    public function handle(): int
    {
        $now = CarbonImmutable::now();

        $reminders = EventReminder::query()
            ->with(['user', 'event'])
            ->where('status', 'pending')
            ->where('remind_at', '<=', $now)
            ->get();

        foreach ($reminders as $reminder) {
            try {
                if (!$reminder->user || !$reminder->event) {
                    $reminder->update(['status' => 'failed']);
                    continue;
                }

                $reminder->user->notify(new EventReminderNotification($reminder));

                $reminder->update([
                    'status' => 'sent',
                    'sent_at' => CarbonImmutable::now(),
                ]);
            } catch (\Throwable $e) {
                Log::error('Event reminder failed', [
                    'reminder_id' => $reminder->id,
                    'error' => $e->getMessage(),
                ]);

                $reminder->update(['status' => 'failed']);
            }
        }

        return Command::SUCCESS;
    }
}

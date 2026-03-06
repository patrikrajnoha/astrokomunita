<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SyncBotsCommand extends Command
{
    protected $signature = 'bots:sync';

    protected $description = 'Normalize bot accounts and safely migrate/remove legacy astrobot.';

    /**
     * @var array<string,list<string>>
     */
    private const USER_REFERENCE_COLUMNS = [
        'posts' => ['user_id'],
        'blog_posts' => ['user_id'],
        'blog_post_comments' => ['user_id'],
        'observations' => ['user_id'],
        'favorites' => ['user_id'],
        'post_likes' => ['user_id'],
        'post_user_bookmarks' => ['user_id'],
        'poll_votes' => ['user_id'],
        'event_reminders' => ['user_id'],
        'event_invites' => ['inviter_user_id', 'invitee_user_id'],
        'reports' => ['reporter_user_id', 'reviewed_by'],
        'manual_events' => ['created_by'],
        'monthly_featured_events' => ['created_by'],
        'newsletter_runs' => ['admin_user_id'],
        'performance_logs' => ['created_by'],
        'moderation_logs' => ['reviewed_by_admin_id'],
        'event_candidates' => ['reviewed_by'],
        'rss_items' => ['reviewed_by'],
        'notifications' => ['user_id'],
        'user_preferences' => ['user_id'],
        'notification_preferences' => ['user_id'],
        'user_notification_preferences' => ['user_id'],
        'email_verifications' => ['user_id'],
        'email_change_requests' => ['user_id'],
        'user_event_follows' => ['user_id'],
        'contests' => ['winner_user_id'],
        'sessions' => ['user_id'],
        'users' => ['user_id'],
        'personal_access_tokens' => ['tokenable_id'],
    ];

    public function handle(): int
    {
        $normalizedBots = [];
        $astrobotRemoved = false;
        $astrobotTarget = null;
        $reassignedRows = 0;
        $astrobotError = null;

        DB::transaction(function () use (&$normalizedBots, &$astrobotRemoved, &$astrobotTarget, &$reassignedRows, &$astrobotError): void {
            foreach (['kozmobot', 'stellarbot'] as $username) {
                $result = $this->normalizeBotAccount($username);
                if ($result === 'updated') {
                    $normalizedBots[] = $username;
                }
            }

            $astrobot = User::query()->where('username', 'astrobot')->first();
            if (! $astrobot) {
                return;
            }

            $target = $this->resolveAstrobotTarget((int) $astrobot->id);
            if (! $target) {
                $astrobotError = 'Found astrobot but no reassignment target (stellarbot, kozmobot, or admin) is available.';
                return;
            }

            $reassignedRows = $this->reassignUserReferences((int) $astrobot->id, (int) $target->id);
            User::query()->whereKey($astrobot->id)->delete();

            $astrobotRemoved = true;
            $astrobotTarget = $target->username;
        });

        $this->line('Normalized bots: '.($normalizedBots === [] ? 'none' : implode(', ', $normalizedBots)));

        if ($astrobotError !== null) {
            $this->error($astrobotError);

            return self::FAILURE;
        }

        if ($astrobotRemoved) {
            $this->info(sprintf(
                'Removed astrobot and reassigned %d row(s) to %s.',
                $reassignedRows,
                (string) $astrobotTarget
            ));
        } else {
            $this->line('No astrobot account found.');
        }

        return self::SUCCESS;
    }

    private function normalizeBotAccount(string $username): string
    {
        $bot = User::query()->where('username', $username)->first();
        if (! $bot) {
            return 'missing';
        }

        $bot->forceFill([
            'is_bot' => true,
            'role' => User::ROLE_BOT,
            'email' => null,
            'requires_email_verification' => false,
            'email_verified_at' => null,
            'is_admin' => false,
        ]);

        if (! $bot->isDirty([
            'is_bot',
            'role',
            'email',
            'requires_email_verification',
            'email_verified_at',
            'is_admin',
        ])) {
            return 'unchanged';
        }

        $bot->save();

        return 'updated';
    }

    private function resolveAstrobotTarget(int $astrobotId): ?User
    {
        $stellarbot = User::query()
            ->where('username', 'stellarbot')
            ->where('id', '!=', $astrobotId)
            ->first();
        if ($stellarbot) {
            return $stellarbot;
        }

        $kozmobot = User::query()
            ->where('username', 'kozmobot')
            ->where('id', '!=', $astrobotId)
            ->first();
        if ($kozmobot) {
            return $kozmobot;
        }

        return User::query()
            ->where('id', '!=', $astrobotId)
            ->where(function ($query): void {
                $query->where('role', User::ROLE_ADMIN)->orWhere('is_admin', true);
            })
            ->orderBy('id')
            ->first();
    }

    private function reassignUserReferences(int $fromUserId, int $toUserId): int
    {
        $updatedRows = 0;

        foreach (self::USER_REFERENCE_COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                $query = DB::table($table)->where($column, $fromUserId);
                if ($table === 'personal_access_tokens') {
                    $query->where('tokenable_type', User::class);
                }

                $updatedRows += $query->update([$column => $toUserId]);
            }
        }

        return $updatedRows;
    }
}

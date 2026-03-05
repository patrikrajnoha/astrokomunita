<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('email')->nullable()->change();
        });

        $this->addBotEmailConstraintIfSupported();
        $this->cleanupAstroBotAndNormalizeBotAccounts();
    }

    public function down(): void
    {
        $this->dropBotEmailConstraintIfSupported();

        Schema::table('users', function (Blueprint $table): void {
            $table->string('email')->nullable(false)->change();
        });
    }

    private function cleanupAstroBotAndNormalizeBotAccounts(): void
    {
        $kozmobot = User::query()->where('username', 'kozmobot')->first();
        $stellarbot = User::query()->where('username', 'stellarbot')->first();

        foreach ([$kozmobot, $stellarbot] as $bot) {
            if (! $bot) {
                continue;
            }

            $bot->forceFill([
                'is_bot' => true,
                'role' => User::ROLE_BOT,
                'email' => null,
                'requires_email_verification' => false,
                'email_verified_at' => null,
                'is_admin' => false,
            ])->save();
        }

        $target = $kozmobot
            ?? User::query()->where('role', User::ROLE_ADMIN)->orWhere('is_admin', true)->orderBy('id')->first();

        $astrobot = User::query()->where('username', 'astrobot')->first();
        if (! $astrobot) {
            return;
        }

        if (! $target || (int) $target->id === (int) $astrobot->id) {
            return;
        }

        $this->reassignUserReferences((int) $astrobot->id, (int) $target->id);
        User::query()->where('id', $astrobot->id)->delete();
    }

    private function reassignUserReferences(int $fromUserId, int $toUserId): void
    {
        $columns = [
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
            'sessions' => ['user_id'],
            'personal_access_tokens' => ['tokenable_id'],
        ];

        foreach ($columns as $table => $tableColumns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($tableColumns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                $query = DB::table($table)->where($column, $fromUserId);
                if ($table === 'personal_access_tokens') {
                    $query->where('tokenable_type', User::class);
                }

                $query->update([$column => $toUserId]);
            }
        }
    }

    private function addBotEmailConstraintIfSupported(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_bot_email_null CHECK (role <> 'bot' OR email IS NULL)");
            return;
        }

        if ($driver === 'mysql') {
            try {
                DB::statement("ALTER TABLE users ADD CONSTRAINT users_bot_email_null CHECK (role <> 'bot' OR email IS NULL)");
            } catch (\Throwable) {
                // Older MySQL / MariaDB variants may ignore unsupported CHECK syntax.
            }
            return;
        }

        if ($driver === 'sqlite') {
            // SQLite keeps check constraints at table-create level for most versions; model-level guard still enforces this.
            return;
        }
    }

    private function dropBotEmailConstraintIfSupported(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_bot_email_null');
            return;
        }

        if ($driver === 'mysql') {
            try {
                DB::statement('ALTER TABLE users DROP CHECK users_bot_email_null');
            } catch (\Throwable) {
                // no-op
            }
        }
    }
};

<?php

use App\Enums\PostAuthorKind;
use App\Enums\PostBotIdentity;
use App\Enums\PostFeedKey;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('feed_key', 20)->default(PostFeedKey::COMMUNITY->value)->after('depth');
            $table->string('author_kind', 20)->default(PostAuthorKind::USER->value)->after('feed_key');
            $table->string('bot_identity', 20)->nullable()->after('author_kind');

            $table->index(['feed_key', 'created_at'], 'posts_feed_key_created_at_index');
            $table->index(['feed_key', 'author_kind'], 'posts_feed_key_author_kind_index');
            $table->index('bot_identity', 'posts_bot_identity_index');
        });

        DB::table('posts')
            ->whereIn('source_name', ['astrobot', 'nasa_rss'])
            ->update([
                'feed_key' => PostFeedKey::ASTRO->value,
                'author_kind' => PostAuthorKind::BOT->value,
                'bot_identity' => PostBotIdentity::STELA->value,
            ]);

        if ($this->usesMysqlLikeLongText()) {
            DB::statement('ALTER TABLE posts MODIFY content LONGTEXT NOT NULL');
        }
    }

    public function down(): void
    {
        if ($this->usesMysqlLikeLongText()) {
            DB::statement('ALTER TABLE posts MODIFY content TEXT NOT NULL');
        }

        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex('posts_feed_key_created_at_index');
            $table->dropIndex('posts_feed_key_author_kind_index');
            $table->dropIndex('posts_bot_identity_index');

            $table->dropColumn([
                'feed_key',
                'author_kind',
                'bot_identity',
            ]);
        });
    }

    private function usesMysqlLikeLongText(): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        return in_array($driver, ['mysql', 'mariadb'], true);
    }
};


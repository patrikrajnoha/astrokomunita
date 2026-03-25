<?php

namespace Database\Seeders;

use App\Enums\PostAuthorKind;
use App\Enums\PostBotIdentity;
use App\Enums\PostFeedKey;
use App\Models\Post;
use App\Models\User;
use App\Support\HashtagParser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DemoFeedSeeder extends Seeder
{
    /**
     * @return array{created:list<string>,updated:list<string>,skipped:list<string>}
     */
    public function seed(): array
    {
        $created = [];
        $updated = [];
        $skipped = [];

        $sourceName = 'demo_seed';
        $now = now();
        $communityUsername = DefaultUsersSeeder::DEFAULT_ADMIN_USERNAME;
        $roots = [
            [
                'uid' => 'demo-community-admin-1',
                'username' => $communityUsername,
                'content' => 'Dnes je super seeing, skusam M42 po zapade. #astro #pozorovanie',
                'feed_key' => PostFeedKey::COMMUNITY->value,
                'author_kind' => PostAuthorKind::USER->value,
                'bot_identity' => null,
                'minutes_ago' => 180,
            ],
            [
                'uid' => 'demo-community-patrik-1',
                'username' => $communityUsername,
                'content' => 'Podarilo sa mi odfotit Jupiter mobilom cez okulár. #jupiter #astrofoto',
                'feed_key' => PostFeedKey::COMMUNITY->value,
                'author_kind' => PostAuthorKind::USER->value,
                'bot_identity' => null,
                'minutes_ago' => 130,
            ],
            [
                'uid' => 'demo-community-admin-2',
                'username' => $communityUsername,
                'content' => 'Tip: nechaj teleskop aklimatizovat aspon 20 minut. #tip #astro',
                'feed_key' => PostFeedKey::COMMUNITY->value,
                'author_kind' => PostAuthorKind::USER->value,
                'bot_identity' => null,
                'minutes_ago' => 95,
            ],
            [
                'uid' => 'demo-astro-stela-1',
                'username' => 'stellarbot',
                'content' => 'Stella: Dnes vecer je vhodny cas na Mesiac v prvej stvrti. #mesiac #astrofeed',
                'feed_key' => PostFeedKey::ASTRO->value,
                'author_kind' => PostAuthorKind::BOT->value,
                'bot_identity' => PostBotIdentity::STELA->value,
                'minutes_ago' => 70,
            ],
            [
                'uid' => 'demo-astro-kozmo-1',
                'username' => 'kozmobot',
                'content' => 'Kozmo: Saturn je nizko nad obzorom, skus pozorovanie tesne po zotmeni. #saturn #astrofeed',
                'feed_key' => PostFeedKey::ASTRO->value,
                'author_kind' => PostAuthorKind::BOT->value,
                'bot_identity' => PostBotIdentity::KOZMO->value,
                'minutes_ago' => 45,
            ],
        ];

        /** @var array<string, Post> $rootByUid */
        $rootByUid = [];

        foreach ($roots as $definition) {
            $user = User::query()->where('username', (string) $definition['username'])->first();
            if (! $user) {
                $skipped[] = (string) $definition['uid'];
                continue;
            }

            $publishedAt = $now->copy()->subMinutes((int) $definition['minutes_ago']);
            $post = Post::query()
                ->where('source_name', $sourceName)
                ->where('source_uid', (string) $definition['uid'])
                ->first();

            $payload = [
                'user_id' => (int) $user->id,
                'parent_id' => null,
                'root_id' => null,
                'depth' => 0,
                'feed_key' => (string) $definition['feed_key'],
                'author_kind' => (string) $definition['author_kind'],
                'bot_identity' => $definition['bot_identity'],
                'content' => (string) $definition['content'],
                'meta' => null,
                'views' => 0,
                'source_name' => $sourceName,
                'source_url' => sprintf('https://astrokomunita.local/demo/%s', (string) $definition['uid']),
                'source_uid' => (string) $definition['uid'],
                'source_published_at' => $publishedAt,
                'is_hidden' => false,
                'moderation_status' => 'ok',
                'moderation_summary' => null,
                'hidden_reason' => null,
                'hidden_at' => null,
                'expires_at' => null,
                'pinned_at' => null,
            ];

            if ($post) {
                $post->fill($payload);
                $post->save();
                $updated[] = (string) $definition['uid'];
            } else {
                $post = Post::query()->create($payload);
                $created[] = (string) $definition['uid'];
            }

            $post->forceFill([
                'created_at' => Carbon::instance($publishedAt),
                'updated_at' => Carbon::instance($publishedAt),
            ])->save();

            HashtagParser::syncHashtags($post, (string) $definition['content']);
            $rootByUid[(string) $definition['uid']] = $post;
        }

        $replies = [
            [
                'uid' => 'demo-reply-patrik-1',
                'username' => $communityUsername,
                'parent_uid' => 'demo-community-admin-1',
                'content' => 'Parada, dnes vyskusam tiez. Dik za tip.',
                'minutes_ago' => 168,
            ],
            [
                'uid' => 'demo-reply-admin-1',
                'username' => $communityUsername,
                'parent_uid' => 'demo-community-patrik-1',
                'content' => 'Super! Ak mozes, hod foto aj do post detailu.',
                'minutes_ago' => 120,
            ],
        ];

        foreach ($replies as $definition) {
            $user = User::query()->where('username', (string) $definition['username'])->first();
            $parent = $rootByUid[(string) $definition['parent_uid']] ?? Post::query()
                ->where('source_name', $sourceName)
                ->where('source_uid', (string) $definition['parent_uid'])
                ->first();

            if (! $user || ! $parent) {
                $skipped[] = (string) $definition['uid'];
                continue;
            }

            $publishedAt = $now->copy()->subMinutes((int) $definition['minutes_ago']);
            $parentFeedKey = $parent->feed_key instanceof PostFeedKey
                ? $parent->feed_key->value
                : (string) ($parent->feed_key ?: PostFeedKey::COMMUNITY->value);
            $reply = Post::query()
                ->where('source_name', $sourceName)
                ->where('source_uid', (string) $definition['uid'])
                ->first();

            $payload = [
                'user_id' => (int) $user->id,
                'parent_id' => (int) $parent->id,
                'root_id' => (int) ($parent->root_id ?: $parent->id),
                'depth' => ((int) ($parent->depth ?? 0)) + 1,
                'feed_key' => $parentFeedKey,
                'author_kind' => PostAuthorKind::USER->value,
                'bot_identity' => null,
                'content' => (string) $definition['content'],
                'meta' => null,
                'views' => 0,
                'source_name' => $sourceName,
                'source_url' => sprintf('https://astrokomunita.local/demo/%s', (string) $definition['uid']),
                'source_uid' => (string) $definition['uid'],
                'source_published_at' => $publishedAt,
                'is_hidden' => false,
                'moderation_status' => 'ok',
                'moderation_summary' => null,
                'hidden_reason' => null,
                'hidden_at' => null,
                'expires_at' => null,
                'pinned_at' => null,
            ];

            if ($reply) {
                $reply->fill($payload);
                $reply->save();
                $updated[] = (string) $definition['uid'];
            } else {
                $reply = Post::query()->create($payload);
                $created[] = (string) $definition['uid'];
            }

            $reply->forceFill([
                'created_at' => Carbon::instance($publishedAt),
                'updated_at' => Carbon::instance($publishedAt),
            ])->save();
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
        ];
    }

    public function run(): void
    {
        $this->seed();
    }
}

<?php

namespace Database\Seeders;

use App\Enums\PostAuthorKind;
use App\Enums\PostBotIdentity;
use App\Enums\PostFeedKey;
use App\Models\Post;
use App\Models\User;
use App\Services\PostService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AstroFeedFoundationSeeder extends Seeder
{
    public function run(): void
    {
        $kozmo = $this->ensureBotUser(
            email: null,
            username: 'kozmobot',
            name: 'Kozmo',
            bio: 'Systemovy astro bot pre hlbsie vysvetlenia.'
        );

        $stela = $this->ensureBotUser(
            email: null,
            username: 'stellarbot',
            name: 'Stela',
            bio: 'Systemovy astro bot pre kratke aktuality.'
        );

        $posts = app(PostService::class);

        $this->upsertBotPost(
            posts: $posts,
            bot: $kozmo,
            sourceUid: 'foundation-seed-kozmo',
            content: $this->buildLongKozmoContent(),
            botIdentity: PostBotIdentity::KOZMO->value
        );

        $this->upsertBotPost(
            posts: $posts,
            bot: $stela,
            sourceUid: 'foundation-seed-stela',
            content: 'Stela test post for AstroFeed separation and comments.',
            botIdentity: PostBotIdentity::STELA->value
        );
    }

    private function ensureBotUser(?string $email, string $username, string $name, string $bio): User
    {
        $user = User::query()->where('username', $username)->first();

        if ($user) {
            $user->forceFill([
                'name' => $name,
                'email' => $email,
                'bio' => $bio,
                'is_bot' => true,
                'role' => User::ROLE_BOT,
                'is_active' => true,
                'email_verified_at' => null,
            ])->save();

            return $user->fresh() ?? $user;
        }

        return User::query()->create([
            'name' => $name,
            'username' => $username,
            'email' => $email,
            'bio' => $bio,
            'password' => Str::random(40),
            'is_bot' => true,
            'role' => User::ROLE_BOT,
            'is_active' => true,
            'email_verified_at' => null,
        ]);
    }

    private function upsertBotPost(PostService $posts, User $bot, string $sourceUid, string $content, string $botIdentity): void
    {
        $sourceName = 'astro_seed';
        $sourceUrl = 'https://astrokomunita.local/seed/' . $sourceUid;

        $existing = Post::query()
            ->where('source_name', $sourceName)
            ->where('source_uid', $sourceUid)
            ->first();

        if ($existing) {
            $existing->fill([
                'user_id' => $bot->id,
                'feed_key' => PostFeedKey::ASTRO->value,
                'author_kind' => PostAuthorKind::BOT->value,
                'bot_identity' => $botIdentity,
                'content' => $content,
                'source_url' => $sourceUrl,
                'source_published_at' => now(),
                'expires_at' => null,
            ])->save();

            return;
        }

        $posts->createPost($bot, $content, null, null, [
            'feed_key' => PostFeedKey::ASTRO->value,
            'author_kind' => PostAuthorKind::BOT->value,
            'bot_identity' => $botIdentity,
            'source_name' => $sourceName,
            'source_url' => $sourceUrl,
            'source_uid' => $sourceUid,
            'source_published_at' => now(),
            'expires_at' => null,
        ]);
    }

    private function buildLongKozmoContent(): string
    {
        $paragraph = 'Kozmo long-form test paragraph. This exists to verify backend accepts and stores long astro bot content without truncation.';

        return implode("\n\n", array_fill(0, 220, $paragraph));
    }
}

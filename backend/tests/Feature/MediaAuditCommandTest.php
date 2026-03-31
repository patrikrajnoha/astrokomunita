<?php

namespace Tests\Feature;

use App\Models\Observation;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaAuditCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_media_audit_command_classifies_valid_missing_legacy_and_invalid_rows(): void
    {
        config()->set('media.disk', 'public');
        config()->set('media.private_disk', 'local');

        Storage::fake('public');
        Storage::fake('local');

        $user = User::factory()->create([
            'avatar_path' => '/storage/avatars/legacy-avatar.png',
            'cover_path' => null,
        ]);

        $bot = User::factory()->bot()->create([
            'username' => 'stellarbot',
            'avatar_path' => 'bots/stellarbot/sb_blue.png',
            'cover_path' => 'covers/stellarbot/missing-cover.png',
        ]);

        $observation = Observation::factory()->for($user)->create();
        Storage::disk('public')->put('observations/1/images/valid.jpg', 'img');
        DB::table('observation_media')->insert([
            'observation_id' => $observation->id,
            'path' => 'observations/1/images/valid.jpg',
            'mime_type' => 'image/jpeg',
            'created_at' => now(),
        ]);
        DB::table('observation_media')->insert([
            'observation_id' => $observation->id,
            'path' => 'observations/1/images/missing.jpg',
            'mime_type' => 'image/jpeg',
            'created_at' => now(),
        ]);

        $pollPost = Post::factory()->for($user)->create();
        DB::table('polls')->insert([
            'id' => 1,
            'post_id' => $pollPost->id,
            'ends_at' => now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Storage::disk('public')->put('polls/1/options/1.png', 'poll');
        Storage::disk('public')->put('polls/1/options/2.png', 'legacy-poll');
        DB::table('poll_options')->insert([
            [
                'id' => 1,
                'poll_id' => 1,
                'text' => 'Valid option',
                'image_path' => 'polls/1/options/1.png',
                'position' => 1,
                'votes_count' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'poll_id' => 1,
                'text' => 'Legacy option',
                'image_path' => 'storage/polls/1/options/2.png',
                'position' => 2,
                'votes_count' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        Post::factory()->for($user)->create([
            'attachment_path' => 'posts/10/attachment.txt',
            'attachment_mime' => 'text/plain',
            'attachment_original_name' => 'note.txt',
            'attachment_size' => 4,
        ]);
        Storage::disk('public')->put('posts/10/attachment.txt', 'note');

        $missingFilePost = Post::factory()->for($user)->create([
            'attachment_path' => 'posts/11/missing.txt',
            'attachment_mime' => 'text/plain',
            'attachment_original_name' => 'missing.txt',
            'attachment_size' => 7,
        ]);

        $imagePost = Post::factory()->for($bot)->create([
            'attachment_path' => 'posts/12/images/12/web.webp',
            'attachment_web_path' => '/api/media/file/posts/12/images/12/web.webp',
            'attachment_original_path' => 'posts/12/images/12/original.jpg',
            'attachment_mime' => 'image/webp',
            'attachment_web_mime' => 'image/webp',
            'attachment_original_mime' => 'image/jpeg',
            'attachment_original_name' => 'apod.jpg',
            'attachment_size' => 1024,
            'attachment_web_size' => 1024,
            'attachment_original_size' => 2048,
            'attachment_variants_json' => json_encode(['processed' => true], JSON_THROW_ON_ERROR),
            'author_kind' => 'bot',
            'bot_identity' => 'stela',
            'source_name' => 'bot_nasa_apod_daily',
        ]);
        Storage::disk('public')->put('posts/12/images/12/web.webp', 'web-image');
        Storage::disk('local')->put('posts/12/images/12/original.jpg', 'original-image');

        $exportPath = storage_path('app/testing/media-audit-report.json');
        if (is_file($exportPath)) {
            @unlink($exportPath);
        }

        $this->artisan('media:audit', [
            '--format' => 'json',
            '--sample' => 10,
            '--export' => $exportPath,
        ])->assertExitCode(0);

        $this->assertFileExists($exportPath);

        $rows = json_decode((string) file_get_contents($exportPath), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($rows);
        $this->assertNotEmpty($rows);

        $byTargetAndId = [];
        foreach ($rows as $row) {
            $key = sprintf('%s.%s#%d', $row['table'], $row['column'], $row['record_id']);
            $byTargetAndId[$key] = $row;
        }

        $this->assertSame('valid', $byTargetAndId['observation_media.path#1']['status']);
        $this->assertSame('missing_file', $byTargetAndId['observation_media.path#2']['status']);
        $this->assertSame('legacy_local_path', $byTargetAndId['poll_options.image_path#2']['status']);
        $this->assertSame('missing_file', $byTargetAndId['posts.attachment_path#' . $missingFilePost->id]['status']);
        $this->assertSame('invalid_url_or_path', $byTargetAndId['posts.attachment_web_path#' . $imagePost->id]['status']);
        $this->assertSame('legacy_local_path', $byTargetAndId['users.avatar_path#' . $user->id]['status']);
        $this->assertSame('valid', $byTargetAndId['users.avatar_path#' . $bot->id]['status']);
        $this->assertSame('missing_file', $byTargetAndId['users.cover_path#' . $bot->id]['status']);
    }
}

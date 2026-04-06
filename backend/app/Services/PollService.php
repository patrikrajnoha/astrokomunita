<?php

namespace App\Services;

use App\Models\Poll;
use App\Models\PollOption;
use App\Models\Post;
use App\Services\Moderation\UploadImageModerationGuard;
use App\Services\Storage\MediaStorageService;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class PollService
{
    public const MIN_DURATION_SECONDS = 300;
    public const MAX_DURATION_SECONDS = 604800;
    public const DEFAULT_DURATION_SECONDS = 86400;

    public const DURATION_PRESET_SECONDS = [
        '5m' => 300,
        '1h' => 3600,
        '1d' => 86400,
        '3d' => 259200,
        '7d' => 604800,
    ];

    public function __construct(
        private readonly MediaStorageService $mediaStorage,
        private readonly UploadImageModerationGuard $uploadImageModeration,
    ) {
    }

    public function pollRelations(?int $viewerUserId = null): array
    {
        if ($viewerUserId) {
            return [
                'poll.options',
                'poll.pollVotes' => fn ($query) => $query->where('user_id', $viewerUserId),
            ];
        }

        return ['poll.options'];
    }

    public function createForPost(Post $post, array $pollInput): Poll
    {
        $endsAt = $this->resolveEndsAt($pollInput);
        $options = $this->normalizeOptions($pollInput);

        $poll = Poll::create([
            'post_id' => $post->id,
            'ends_at' => $endsAt,
        ]);

        foreach ($options as $index => $optionInput) {
            $option = $poll->pollOptions()->create([
                'text' => $optionInput['text'],
                'position' => $index + 1,
                'votes_count' => 0,
            ]);

            if ($optionInput['image'] instanceof UploadedFile) {
                $this->uploadImageModeration->assertUploadedFileAllowed(
                    $optionInput['image'],
                    sprintf('poll.options.%d.image', $index),
                    'poll_option_image'
                );

                $path = $this->mediaStorage->storePollOptionImage($optionInput['image'], (int) $poll->id, (int) $option->id);
                $option->image_path = $path;
                $option->save();
            }
        }

        return $poll;
    }

    public function resolveEndsAt(array $pollInput): CarbonImmutable
    {
        $now = CarbonImmutable::now();

        if (!empty($pollInput['ends_at'])) {
            return CarbonImmutable::parse((string) $pollInput['ends_at']);
        }

        if (isset($pollInput['duration_seconds'])) {
            return $now->addSeconds((int) $pollInput['duration_seconds']);
        }

        if (isset($pollInput['ends_in_seconds'])) {
            return $now->addSeconds((int) $pollInput['ends_in_seconds']);
        }

        $preset = (string) ($pollInput['duration_preset'] ?? '1d');
        $seconds = self::DURATION_PRESET_SECONDS[$preset] ?? self::DEFAULT_DURATION_SECONDS;

        return $now->addSeconds($seconds);
    }

    public function toPayload(?Poll $poll, ?int $viewerUserId = null): ?array
    {
        if (!$poll) {
            return null;
        }

        $poll->loadMissing(['options']);
        $options = $poll->options->sortBy('position')->values();
        $totalVotes = (int) $options->sum('votes_count');
        $isClosed = now()->greaterThanOrEqualTo($poll->ends_at);
        $endsInSeconds = max(0, now()->diffInSeconds($poll->ends_at, false));

        $myVoteOptionId = null;
        if ($viewerUserId) {
            if ($poll->relationLoaded('pollVotes')) {
                $myVoteOptionId = (int) optional($poll->pollVotes->first())->poll_option_id ?: null;
            } else {
                $myVoteOptionId = DB::table('poll_votes')
                    ->where('poll_id', $poll->id)
                    ->where('user_id', $viewerUserId)
                    ->value('poll_option_id');
                $myVoteOptionId = $myVoteOptionId ? (int) $myVoteOptionId : null;
            }
        }

        $winnerVoteCount = null;
        if ($isClosed && $options->isNotEmpty()) {
            $winnerVoteCount = (int) $options->max('votes_count');
        }

        return [
            'id' => (int) $poll->id,
            'ends_at' => optional($poll->ends_at)->toISOString(),
            'is_closed' => $isClosed,
            'total_votes' => $totalVotes,
            'ends_in_seconds' => $endsInSeconds,
            'user_has_voted' => $myVoteOptionId !== null,
            'chosen_option_id' => $myVoteOptionId,
            'my_vote_option_id' => $myVoteOptionId,
            'options' => $options->map(function (PollOption $option) use ($totalVotes, $isClosed, $winnerVoteCount) {
                $votesCount = (int) $option->votes_count;
                $percent = $totalVotes > 0 ? (int) round(($votesCount / $totalVotes) * 100) : 0;

                return [
                    'id' => (int) $option->id,
                    'text' => $option->text,
                    'image_url' => $this->mediaStorage->publicMediaUrl($option->image_path),
                    'votes_count' => $votesCount,
                    'percent' => $percent,
                    'is_winner' => $isClosed && $winnerVoteCount !== null && $votesCount === $winnerVoteCount,
                ];
            })->values()->all(),
        ];
    }

    private function normalizeOptions(array $pollInput): array
    {
        $normalized = [];

        foreach ((array) ($pollInput['options'] ?? []) as $option) {
            if (is_array($option)) {
                $normalized[] = [
                    'text' => trim((string) ($option['text'] ?? '')),
                    'image' => $option['image'] ?? null,
                ];
                continue;
            }

            $normalized[] = [
                'text' => trim((string) $option),
                'image' => null,
            ];
        }

        return array_values($normalized);
    }
}

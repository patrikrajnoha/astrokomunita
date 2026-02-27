<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contest;
use App\Models\Post;
use App\Services\NotificationService;
use App\Support\HashtagParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ContestController extends Controller
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min($perPage, 50));

        $items = Contest::query()
            ->with(['winnerPost:id,user_id,created_at', 'winnerUser:id,username'])
            ->latest()
            ->paginate($perPage);

        return response()->json($items);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'hashtag' => ['nullable', 'string', 'max:64'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'status' => ['nullable', Rule::in(['draft', 'active', 'finished'])],
        ]);

        $normalizedHashtag = $this->normalizeHashtag($validated['hashtag'] ?? 'sutazim');
        if (Contest::query()->where('hashtag', $normalizedHashtag)->exists()) {
            throw ValidationException::withMessages([
                'hashtag' => 'The hashtag has already been taken.',
            ]);
        }

        $contest = Contest::query()->create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'hashtag' => $normalizedHashtag,
            'starts_at' => $validated['starts_at'],
            'ends_at' => $validated['ends_at'],
            'status' => $validated['status'] ?? 'draft',
        ]);

        return response()->json($contest, 201);
    }

    public function update(Request $request, Contest $contest): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'hashtag' => ['sometimes', 'required', 'string', 'max:64'],
            'starts_at' => ['sometimes', 'required', 'date'],
            'ends_at' => ['sometimes', 'required', 'date'],
            'status' => ['sometimes', Rule::in(['draft', 'active', 'finished'])],
        ]);

        if (array_key_exists('starts_at', $validated) || array_key_exists('ends_at', $validated)) {
            $startsAt = array_key_exists('starts_at', $validated)
                ? $validated['starts_at']
                : $contest->starts_at;
            $endsAt = array_key_exists('ends_at', $validated)
                ? $validated['ends_at']
                : $contest->ends_at;

            if (strtotime((string) $endsAt) <= strtotime((string) $startsAt)) {
                throw ValidationException::withMessages([
                    'ends_at' => 'The ends_at field must be a date after starts_at.',
                ]);
            }
        }

        if (array_key_exists('hashtag', $validated)) {
            $validated['hashtag'] = $this->normalizeHashtag($validated['hashtag']);
            $exists = Contest::query()
                ->where('hashtag', $validated['hashtag'])
                ->where('id', '!=', $contest->id)
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'hashtag' => 'The hashtag has already been taken.',
                ]);
            }
        }

        $contest->fill($validated);
        $contest->save();

        return response()->json($contest->fresh(['winnerPost:id,user_id,created_at', 'winnerUser:id,username']));
    }

    public function selectWinner(Request $request, Contest $contest): JsonResponse
    {
        $validated = $request->validate([
            'post_id' => ['required', 'integer', 'exists:posts,id'],
        ]);

        $resolved = DB::transaction(function () use ($contest, $validated) {
            $lockedContest = Contest::query()->whereKey($contest->id)->lockForUpdate()->firstOrFail();

            if ($lockedContest->status === 'finished' || $lockedContest->winner_post_id) {
                throw ValidationException::withMessages([
                    'contest' => 'Winner is already selected for this contest.',
                ]);
            }

            if ($lockedContest->ends_at && now()->lessThan($lockedContest->ends_at)) {
                throw ValidationException::withMessages([
                    'contest' => 'Winner cannot be selected before contest ends.',
                ]);
            }

            $post = Post::query()->with('user:id,username')->findOrFail((int) $validated['post_id']);
            if (!$this->isEligiblePost($lockedContest, $post)) {
                throw ValidationException::withMessages([
                    'post_id' => 'Selected post is not eligible for this contest.',
                ]);
            }

            $lockedContest->winner_post_id = $post->id;
            $lockedContest->winner_user_id = $post->user_id;
            $lockedContest->status = 'finished';
            if ($lockedContest->ends_at && now()->lt($lockedContest->ends_at)) {
                $lockedContest->ends_at = now();
            }
            $lockedContest->save();

            $this->notifications->createContestWinner(
                (int) $post->user_id,
                (int) $lockedContest->id,
                (string) $lockedContest->name,
                (int) $post->id
            );

            return $lockedContest->fresh(['winnerPost:id,user_id,created_at', 'winnerUser:id,username']);
        });

        return response()->json($resolved);
    }

    private function normalizeHashtag(string $value): string
    {
        $normalized = strtolower(ltrim(trim($value), '#'));

        if (!preg_match('/^[a-z0-9_]{1,32}$/', $normalized)) {
            throw ValidationException::withMessages([
                'hashtag' => 'Hashtag must contain only letters, numbers, underscore and max length 32.',
            ]);
        }

        return $normalized;
    }

    private function isEligiblePost(Contest $contest, Post $post): bool
    {
        $createdAt = $post->created_at;
        if (!$createdAt || !$contest->starts_at || !$contest->ends_at) {
            return false;
        }

        if ($createdAt->lt($contest->starts_at) || $createdAt->gt($contest->ends_at)) {
            return false;
        }

        $hashtags = HashtagParser::extract((string) $post->content);

        return $hashtags->contains(strtolower($contest->hashtag));
    }
}

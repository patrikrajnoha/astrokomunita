<?php

namespace App\Services;

use App\Enums\EventInviteStatus;
use App\Models\EventInvite;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class UserActivityService
{
    private const CACHE_TTL_SECONDS = 300;

    /**
     * @return array{
     *     last_login_at: string|null,
     *     posts_count: int,
     *     event_participations_count: int
     * }
     */
    public function getActivity(User $user): array
    {
        return Cache::remember(
            $this->cacheKey((int) $user->id),
            now()->addSeconds(self::CACHE_TTL_SECONDS),
            function () use ($user): array {
                return [
                    'last_login_at' => $user->last_login_at?->toIso8601String(),
                    'posts_count' => (int) Post::query()
                        ->where('user_id', $user->id)
                        ->count(),
                    // Participation means the user accepted an event invite.
                    'event_participations_count' => (int) EventInvite::query()
                        ->where('status', EventInviteStatus::Accepted->value)
                        ->where(function ($query) use ($user): void {
                            $query->where('invitee_user_id', $user->id);

                            if (!empty($user->email)) {
                                $query->orWhereRaw('LOWER(invitee_email) = ?', [strtolower((string) $user->email)]);
                            }
                        })
                        ->count(),
                ];
            }
        );
    }

    public function forgetActivity(User|int $user): void
    {
        $userId = $user instanceof User ? (int) $user->id : (int) $user;
        if ($userId <= 0) {
            return;
        }

        Cache::forget($this->cacheKey($userId));
    }

    private function cacheKey(int $userId): string
    {
        return 'user_activity:' . $userId;
    }
}

<?php

namespace App\Services;

use App\Models\Post;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class PostPayloadService
{
    public function __construct(
        private readonly PollService $polls,
    ) {
    }

    public function serializePost(Post $post, ?User $viewer = null): array
    {
        $data = $post->toArray();
        $data['poll'] = $this->polls->toPayload(
            $post->relationLoaded('poll') ? $post->getRelation('poll') : null,
            $viewer?->id
        );

        return $data;
    }

    public function serializePaginator(LengthAwarePaginator $paginator, ?User $viewer = null): LengthAwarePaginator
    {
        return $paginator->through(fn (Post $post) => $this->serializePost($post, $viewer));
    }

    public function serializeCollection(Collection $posts, ?User $viewer = null): Collection
    {
        return $posts->map(fn (Post $post) => $this->serializePost($post, $viewer));
    }

    public function serializeNestedReplies(Collection $replies, ?User $viewer = null): array
    {
        return $replies->map(function (Post $reply) use ($viewer) {
            $data = $this->serializePost($reply, $viewer);

            $rawNestedReplies = $reply->relationLoaded('replies')
                ? $reply->getRelation('replies')
                : collect();

            if ($rawNestedReplies instanceof Collection) {
                $data['replies'] = $this->serializeNestedReplies($rawNestedReplies, $viewer);
            } else {
                $data['replies'] = [];
            }

            return $data;
        })->values()->all();
    }
}

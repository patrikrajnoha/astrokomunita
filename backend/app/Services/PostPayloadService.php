<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Post;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class PostPayloadService
{
    /** @var array<int, array<string, mixed>|null> */
    private array $attachedEventCache = [];

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
        $data['attached_event'] = $this->resolveAttachedEventPayload($post);

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

    private function resolveAttachedEventPayload(Post $post): ?array
    {
        $eventId = (int) data_get($post->meta, 'event.event_id', 0);
        if ($eventId <= 0) {
            return null;
        }

        if (array_key_exists($eventId, $this->attachedEventCache)) {
            return $this->attachedEventCache[$eventId];
        }

        $event = Event::query()
            ->select(['id', 'title', 'type', 'start_at', 'end_at', 'max_at', 'short'])
            ->find($eventId);

        if (!$event) {
            $this->attachedEventCache[$eventId] = null;
            return null;
        }

        $payload = [
            'id' => (int) $event->id,
            'title' => (string) $event->title,
            'type' => $event->type ? (string) $event->type : null,
            'short' => $event->short ? (string) $event->short : null,
            'start_at' => optional($event->start_at)?->toIso8601String(),
            'end_at' => optional($event->end_at)?->toIso8601String(),
            'max_at' => optional($event->max_at)?->toIso8601String(),
        ];

        $this->attachedEventCache[$eventId] = $payload;

        return $payload;
    }
}

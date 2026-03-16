<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Observation;
use App\Models\Post;
use App\Models\User;
use App\Services\Storage\MediaStorageService;
use App\Support\PublicUserPayload;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class PostPayloadService
{
    /**
     * @var list<string>
     */
    private const STRIPPED_POST_KEYS = [
        'attachment_original_path',
        'attachment_web_path',
        'attachment_original_mime',
        'attachment_web_mime',
        'attachment_original_size',
        'attachment_variants_json',
        'attachment_moderation_summary',
        'moderation_summary',
        'source_uid',
        'bot_item_id',
        'hidden_reason',
        'hidden_at',
    ];

    /** @var array<int, array<string, mixed>|null> */
    private array $attachedEventCache = [];

    /** @var array<int, Observation|null> */
    private array $attachedObservationCache = [];

    public function __construct(
        private readonly PollService $polls,
        private readonly MediaStorageService $mediaStorage,
        private readonly ObservationPayloadService $observationPayloads,
        private readonly PublicUserPayload $publicUsers,
    ) {
    }

    public function serializePost(Post $post, ?User $viewer = null): array
    {
        $data = $post->toArray();
        $data = $this->sanitizePostPayload($data);
        $data['poll'] = $this->polls->toPayload(
            $post->relationLoaded('poll') ? $post->getRelation('poll') : null,
            $viewer?->id
        );
        $data['attached_event'] = $this->resolveAttachedEventPayload($post);
        $data['attached_observation'] = $this->resolveAttachedObservationPayload($post, $viewer);
        $data['feed_item_type'] = $data['attached_observation'] ? 'observation' : 'post';

        return $data;
    }

    public function serializePaginator(LengthAwarePaginator $paginator, ?User $viewer = null): LengthAwarePaginator
    {
        $this->primeAttachedObservations($paginator->getCollection());

        return $paginator->through(fn (Post $post) => $this->serializePost($post, $viewer));
    }

    public function serializeCollection(Collection $posts, ?User $viewer = null): Collection
    {
        $this->primeAttachedObservations($posts);

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

    private function resolveAttachedObservationPayload(Post $post, ?User $viewer = null): ?array
    {
        $observationId = (int) data_get($post->meta, 'observation.observation_id', 0);
        if ($observationId <= 0) {
            return null;
        }

        if (!array_key_exists($observationId, $this->attachedObservationCache)) {
            $this->attachedObservationCache[$observationId] = Observation::query()
                ->with([
                    'user:id,name,username,location,bio,is_admin,avatar_path,avatar_mode,avatar_color,avatar_icon,avatar_seed',
                    'event:id,title,type,start_at,end_at,max_at,short',
                    'media',
                ])
                ->find($observationId);
        }

        $observation = $this->attachedObservationCache[$observationId];
        if (!$observation) {
            return null;
        }

        $canView = $observation->is_public
            || ($viewer && ((int) $observation->user_id === (int) $viewer->id || $viewer->isAdmin()));

        if (!$canView) {
            return null;
        }

        return $this->observationPayloads->serializeObservation($observation, $viewer);
    }

    public function primeAttachedObservations(Collection $posts): void
    {
        $observationIds = $this->extractAttachedObservationIds($posts);
        if ($observationIds === []) {
            return;
        }

        $idsToLoad = array_values(array_filter(
            $observationIds,
            fn (int $id): bool => !array_key_exists($id, $this->attachedObservationCache)
        ));

        if ($idsToLoad === []) {
            return;
        }

        $loaded = Observation::query()
            ->with([
                'user:id,name,username,location,bio,is_admin,avatar_path,avatar_mode,avatar_color,avatar_icon,avatar_seed',
                'event:id,title,type,start_at,end_at,max_at,short',
                'media',
            ])
            ->whereIn('id', $idsToLoad)
            ->get()
            ->keyBy('id');

        foreach ($idsToLoad as $id) {
            $this->attachedObservationCache[$id] = $loaded->get($id);
        }

        if (config('app.debug')) {
            Log::debug('Feed attached observations preloaded.', [
                'requested_ids' => count($observationIds),
                'loaded_ids' => $loaded->count(),
                'cache_size' => count($this->attachedObservationCache),
            ]);
        }
    }

    /**
     * @return array<int, int>
     */
    private function extractAttachedObservationIds(Collection $posts): array
    {
        return $posts
            ->map(static fn (Post $post): int => (int) data_get($post->meta, 'observation.observation_id', 0))
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Ensure avatar/cover URLs are available in nested post payload user objects.
     *
     * @param array<string, mixed> $postData
     * @return array<string, mixed>
     */
    private function sanitizePostPayload(array $postData): array
    {
        foreach (self::STRIPPED_POST_KEYS as $key) {
            unset($postData[$key]);
        }

        if (isset($postData['user']) && is_array($postData['user'])) {
            $postData['user'] = $this->publicUsers->fromArray($postData['user']);
        }

        foreach (['parent', 'root'] as $relationKey) {
            if (isset($postData[$relationKey]) && is_array($postData[$relationKey])) {
                $postData[$relationKey] = $this->sanitizePostPayload($postData[$relationKey]);
            }
        }

        if (isset($postData['replies']) && is_array($postData['replies'])) {
            $postData['replies'] = array_map(function ($reply) {
                if (!is_array($reply)) {
                    return $reply;
                }

                return $this->sanitizePostPayload($reply);
            }, $postData['replies']);
        }

        return $postData;
    }
}

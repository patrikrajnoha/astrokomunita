<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Observation;
use App\Models\ObservationMedia;
use App\Models\User;
use App\Policies\ObservationPolicy;
use App\Services\Storage\MediaStorageService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ObservationPayloadService
{
    /** @var array<int, array<string, mixed>|null> */
    private array $eventCache = [];

    public function __construct(
        private readonly MediaStorageService $mediaStorage,
        private readonly ObservationPolicy $policy,
    ) {
    }

    public function serializeObservation(Observation $observation, ?User $viewer = null): array
    {
        $observation->loadMissing([
            'user:id,name,username,location,bio,is_admin,avatar_path,cover_path',
            'event:id,title,type,start_at,end_at,max_at,short',
            'media',
        ]);

        $canViewPreciseLocation = $this->policy->viewPreciseLocation($viewer, $observation);

        return [
            'id' => (int) $observation->id,
            'user_id' => (int) $observation->user_id,
            'event_id' => $observation->event_id !== null ? (int) $observation->event_id : null,
            'feed_post_id' => $observation->feed_post_id !== null ? (int) $observation->feed_post_id : null,
            'title' => (string) $observation->title,
            'description' => $observation->description !== null ? (string) $observation->description : null,
            'observed_at' => optional($observation->observed_at)?->toIso8601String(),
            'location_lat' => $canViewPreciseLocation && $observation->location_lat !== null
                ? (float) $observation->location_lat
                : null,
            'location_lng' => $canViewPreciseLocation && $observation->location_lng !== null
                ? (float) $observation->location_lng
                : null,
            'location_name' => $observation->location_name !== null ? (string) $observation->location_name : null,
            'visibility_rating' => $observation->visibility_rating !== null ? (int) $observation->visibility_rating : null,
            'equipment' => $observation->equipment !== null ? (string) $observation->equipment : null,
            'is_public' => (bool) $observation->is_public,
            'created_at' => optional($observation->created_at)?->toIso8601String(),
            'updated_at' => optional($observation->updated_at)?->toIso8601String(),
            'user' => $this->serializeUser($observation->user),
            'event' => $this->serializeEvent($observation),
            'media' => $this->serializeMediaCollection(
                $observation->relationLoaded('media') ? $observation->getRelation('media') : collect()
            ),
            'can_edit' => $viewer
                ? ((int) $viewer->id === (int) $observation->user_id || $viewer->isAdmin())
                : false,
        ];
    }

    public function serializePaginator(LengthAwarePaginator $paginator, ?User $viewer = null): LengthAwarePaginator
    {
        return $paginator->through(fn (Observation $observation) => $this->serializeObservation($observation, $viewer));
    }

    public function serializeCollection(Collection $observations, ?User $viewer = null): Collection
    {
        return $observations->map(fn (Observation $observation) => $this->serializeObservation($observation, $viewer));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function serializeUser(?User $user): ?array
    {
        if (!$user) {
            return null;
        }

        $data = $user->toArray();

        return $this->ensureUserMediaUrls($data);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function serializeEvent(Observation $observation): ?array
    {
        $eventId = (int) ($observation->event_id ?? 0);
        if ($eventId <= 0) {
            return null;
        }

        if (array_key_exists($eventId, $this->eventCache)) {
            return $this->eventCache[$eventId];
        }

        $event = $observation->relationLoaded('event')
            ? $observation->getRelation('event')
            : Event::query()
                ->select(['id', 'title', 'type', 'start_at', 'end_at', 'max_at', 'short'])
                ->find($eventId);

        if (!$event) {
            $this->eventCache[$eventId] = null;

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

        $this->eventCache[$eventId] = $payload;

        return $payload;
    }

    /**
     * @param Collection<int, ObservationMedia> $media
     * @return array<int, array<string, mixed>>
     */
    private function serializeMediaCollection(Collection $media): array
    {
        return $media->map(function (ObservationMedia $item): array {
            $path = trim((string) $item->path);

            return [
                'id' => (int) $item->id,
                'path' => $path !== '' ? $path : null,
                'url' => $path !== '' ? $this->mediaStorage->absoluteUrl($path) : null,
                'mime_type' => $item->mime_type !== null ? (string) $item->mime_type : null,
                'width' => $item->width !== null ? (int) $item->width : null,
                'height' => $item->height !== null ? (int) $item->height : null,
                'created_at' => optional($item->created_at)?->toIso8601String(),
            ];
        })->values()->all();
    }

    /**
     * @param array<string, mixed> $userData
     * @return array<string, mixed>
     */
    private function ensureUserMediaUrls(array $userData): array
    {
        $avatarUrl = trim((string) ($userData['avatar_url'] ?? ''));
        $avatarPath = trim((string) ($userData['avatar_path'] ?? ''));
        if ($avatarUrl === '' && $avatarPath !== '') {
            $resolvedAvatar = $this->mediaStorage->absoluteUrl($avatarPath);
            if (is_string($resolvedAvatar) && trim($resolvedAvatar) !== '') {
                $userData['avatar_url'] = $resolvedAvatar;
            }
        }

        $coverUrl = trim((string) ($userData['cover_url'] ?? ''));
        $coverPath = trim((string) ($userData['cover_path'] ?? ''));
        if ($coverUrl === '' && $coverPath !== '') {
            $resolvedCover = $this->mediaStorage->absoluteUrl($coverPath);
            if (is_string($resolvedCover) && trim($resolvedCover) !== '') {
                $userData['cover_url'] = $resolvedCover;
            }
        }

        return $userData;
    }
}

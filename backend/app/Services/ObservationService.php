<?php

namespace App\Services;

use App\Enums\PostAuthorKind;
use App\Enums\PostFeedKey;
use App\Models\Observation;
use App\Models\ObservationMedia;
use App\Models\Post;
use App\Models\User;
use App\Services\Moderation\UploadImageModerationGuard;
use App\Services\Storage\MediaStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ObservationService
{
    public function __construct(
        private readonly MediaStorageService $mediaStorage,
        private readonly UploadImageModerationGuard $uploadImageModeration,
    ) {
    }

    /**
     * @param array<string, mixed> $attributes
     * @param array<int, UploadedFile> $images
     */
    public function createObservation(User $user, array $attributes, array $images): Observation
    {
        return DB::transaction(function () use ($user, $attributes, $images) {
            $observation = new Observation();
            $observation->user_id = (int) $user->id;
            $observation->event_id = $this->normalizeNullableInt($attributes, 'event_id');
            $observation->title = trim((string) ($attributes['title'] ?? ''));
            $observation->description = $this->normalizeNullableString($attributes, 'description');
            $observation->observed_at = $attributes['observed_at'] ?? null;
            $observation->location_lat = $this->normalizeNullableFloat($attributes, 'location_lat');
            $observation->location_lng = $this->normalizeNullableFloat($attributes, 'location_lng');
            $observation->location_name = $this->normalizeNullableString($attributes, 'location_name');
            $observation->visibility_rating = $this->normalizeNullableInt($attributes, 'visibility_rating');
            $observation->equipment = $this->normalizeNullableString($attributes, 'equipment');
            $observation->is_public = $this->normalizeBool($attributes, 'is_public', true);
            $observation->save();

            $this->assertMediaLimit($observation, $images);
            $this->storeImages($observation, $images);
            $this->syncFeedMirror($observation);

            return $observation->fresh()->load(['user', 'event', 'media']);
        });
    }

    /**
     * @param array<string, mixed> $attributes
     * @param array<int, UploadedFile> $images
     * @param array<int, int|string> $removeMediaIds
     */
    public function updateObservation(
        Observation $observation,
        array $attributes,
        array $images = [],
        array $removeMediaIds = []
    ): Observation {
        return DB::transaction(function () use ($observation, $attributes, $images, $removeMediaIds) {
            if (array_key_exists('event_id', $attributes)) {
                $observation->event_id = $this->normalizeNullableInt($attributes, 'event_id');
            }
            if (array_key_exists('title', $attributes)) {
                $observation->title = trim((string) ($attributes['title'] ?? ''));
            }
            if (array_key_exists('description', $attributes)) {
                $observation->description = $this->normalizeNullableString($attributes, 'description');
            }
            if (array_key_exists('observed_at', $attributes)) {
                $observation->observed_at = $attributes['observed_at'] ?? null;
            }
            if (array_key_exists('location_lat', $attributes)) {
                $observation->location_lat = $this->normalizeNullableFloat($attributes, 'location_lat');
            }
            if (array_key_exists('location_lng', $attributes)) {
                $observation->location_lng = $this->normalizeNullableFloat($attributes, 'location_lng');
            }
            if (array_key_exists('location_name', $attributes)) {
                $observation->location_name = $this->normalizeNullableString($attributes, 'location_name');
            }
            if (array_key_exists('visibility_rating', $attributes)) {
                $observation->visibility_rating = $this->normalizeNullableInt($attributes, 'visibility_rating');
            }
            if (array_key_exists('equipment', $attributes)) {
                $observation->equipment = $this->normalizeNullableString($attributes, 'equipment');
            }
            if (array_key_exists('is_public', $attributes)) {
                $observation->is_public = $this->normalizeBool($attributes, 'is_public', true);
            }

            $observation->save();

            $mediaToRemove = $this->resolveMediaToRemove($observation, $removeMediaIds);
            $this->assertMediaLimit($observation, $images, $mediaToRemove->count());
            $this->deleteMediaItems($mediaToRemove);
            $this->storeImages($observation, $images);
            $this->syncFeedMirror($observation);

            return $observation->fresh()->load(['user', 'event', 'media']);
        });
    }

    public function deleteObservation(Observation $observation): void
    {
        DB::transaction(function () use ($observation): void {
            $observation->loadMissing('media');

            /** @var Collection<int, ObservationMedia> $media */
            $media = $observation->getRelation('media');
            foreach ($media as $item) {
                $this->mediaStorage->delete($item->path);
            }

            $this->deleteFeedMirror($observation);
            $observation->delete();
        });
    }

    /**
     * @param array<int, UploadedFile> $images
     */
    private function storeImages(Observation $observation, array $images): void
    {
        foreach ($images as $index => $image) {
            if (!$image instanceof UploadedFile) {
                continue;
            }

            $this->uploadImageModeration->assertUploadedFileAllowed(
                $image,
                'images.' . (int) $index,
                'observation_image'
            );

            $path = $this->mediaStorage->storeObservationImage($image, (int) $observation->id);
            [$width, $height] = $this->resolveDimensions($image);

            $observation->media()->create([
                'path' => $path,
                'mime_type' => $this->resolveMime($image),
                'width' => $width,
                'height' => $height,
            ]);
        }
    }

    /**
     * @param array<int, int|string> $removeMediaIds
     * @return Collection<int, ObservationMedia>
     */
    private function resolveMediaToRemove(Observation $observation, array $removeMediaIds): Collection
    {
        $normalizedIds = collect($removeMediaIds)
            ->map(static fn (mixed $id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($normalizedIds->isEmpty()) {
            return collect();
        }

        $toDelete = $observation->media()
            ->whereIn('id', $normalizedIds->all())
            ->get();

        if ($toDelete->count() !== $normalizedIds->count()) {
            throw ValidationException::withMessages([
                'remove_media_ids' => ['Some selected media items do not belong to this observation.'],
            ]);
        }

        return $toDelete;
    }

    private function syncFeedMirror(Observation $observation): void
    {
        if (!($observation->is_public ?? true)) {
            $this->deleteFeedMirror($observation);

            return;
        }

        $post = null;
        $postId = (int) ($observation->feed_post_id ?? 0);
        if ($postId > 0) {
            $post = Post::query()->find($postId);
        }
        if (!$post) {
            $post = Post::query()
                ->where('source_name', 'observation')
                ->where('source_uid', 'observation-' . (int) $observation->id)
                ->first();
        }

        $payload = [
            'user_id' => (int) $observation->user_id,
            'parent_id' => null,
            'root_id' => null,
            'depth' => 0,
            'feed_key' => PostFeedKey::COMMUNITY->value,
            'author_kind' => PostAuthorKind::USER->value,
            'bot_identity' => null,
            'content' => $this->feedMirrorContent($observation),
            'meta' => $this->feedMirrorMeta($observation),
            'source_name' => 'observation',
            'source_uid' => 'observation-' . (int) $observation->id,
            'source_url' => null,
            'source_published_at' => $observation->observed_at,
            'is_hidden' => false,
            'moderation_status' => 'ok',
            'hidden_reason' => null,
            'hidden_at' => null,
        ];

        if ($post) {
            $post->fill($payload);
            $post->save();
        } else {
            $post = Post::query()->create($payload);
        }

        if ((int) ($observation->feed_post_id ?? 0) !== (int) $post->id) {
            $observation->feed_post_id = (int) $post->id;
            $observation->save();
        }
    }

    private function deleteFeedMirror(Observation $observation): void
    {
        $postIds = [];

        $linkedPostId = (int) ($observation->feed_post_id ?? 0);
        if ($linkedPostId > 0) {
            $postIds[] = $linkedPostId;
        }

        $sourceUid = 'observation-' . (int) $observation->id;
        $sourcePostIds = Post::query()
            ->where('source_name', 'observation')
            ->where('source_uid', $sourceUid)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        $postIds = array_values(array_unique(array_merge($postIds, $sourcePostIds)));
        if ($postIds !== []) {
            Post::query()->whereIn('id', $postIds)->delete();
        }

        if ($observation->feed_post_id !== null) {
            $observation->feed_post_id = null;
            $observation->save();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function feedMirrorMeta(Observation $observation): array
    {
        $description = trim((string) ($observation->description ?? ''));
        $media = $observation->media()
            ->select(['id', 'path'])
            ->orderBy('id')
            ->get();
        $firstMedia = $media->first();

        $meta = [
            'observation' => [
                'observation_id' => (int) $observation->id,
                'title' => (string) $observation->title,
                'description_excerpt' => $description !== '' ? Str::limit($description, 220) : null,
                'observed_at' => optional($observation->observed_at)?->toIso8601String(),
                'location_name' => $observation->location_name ? (string) $observation->location_name : null,
                'media_count' => $media->count(),
                'preview_media_id' => $firstMedia ? (int) $firstMedia->id : null,
            ],
        ];

        if ($observation->event_id) {
            $meta['event'] = [
                'event_id' => (int) $observation->event_id,
                'attached_type' => 'event',
            ];
        }

        return $meta;
    }

    private function feedMirrorContent(Observation $observation): string
    {
        $title = trim((string) $observation->title);
        $description = trim((string) ($observation->description ?? ''));

        if ($description === '') {
            return 'Pozorovanie: ' . $title;
        }

        return 'Pozorovanie: ' . $title . ' - ' . Str::limit($description, 160);
    }

    /**
     * @param Collection<int, ObservationMedia> $media
     */
    private function deleteMediaItems(Collection $media): void
    {
        /** @var ObservationMedia $item */
        foreach ($media as $item) {
            $this->mediaStorage->delete($item->path);
            $item->delete();
        }
    }

    /**
     * @param array<int, UploadedFile> $newImages
     */
    private function assertMediaLimit(Observation $observation, array $newImages, int $removingCount = 0): void
    {
        $maxImages = max(1, (int) config('media.observation_image_max_count', 6));
        $existingCount = (int) $observation->media()->count();
        $newCount = $this->countUploadedImages($newImages);
        $remainingCount = max(0, $existingCount - max(0, $removingCount));

        if (($remainingCount + $newCount) <= $maxImages) {
            return;
        }

        throw ValidationException::withMessages([
            'images' => [sprintf('Maximum %d images are allowed per observation.', $maxImages)],
        ]);
    }

    /**
     * @param array<int, UploadedFile> $images
     */
    private function countUploadedImages(array $images): int
    {
        return (int) collect($images)
            ->filter(static fn (mixed $image): bool => $image instanceof UploadedFile)
            ->count();
    }

    /**
     * @return array{0:?int,1:?int}
     */
    private function resolveDimensions(UploadedFile $image): array
    {
        $path = $image->getRealPath();
        if (!$path) {
            return [null, null];
        }

        $dimensions = @getimagesize($path);
        if (!is_array($dimensions) || count($dimensions) < 2) {
            return [null, null];
        }

        return [
            isset($dimensions[0]) ? (int) $dimensions[0] : null,
            isset($dimensions[1]) ? (int) $dimensions[1] : null,
        ];
    }

    private function resolveMime(UploadedFile $image): ?string
    {
        $mime = trim((string) ($image->getMimeType() ?: $image->getClientMimeType()));

        return $mime !== '' ? strtolower($mime) : null;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function normalizeNullableInt(array $attributes, string $key): ?int
    {
        if (!array_key_exists($key, $attributes) || $attributes[$key] === null || $attributes[$key] === '') {
            return null;
        }

        return (int) $attributes[$key];
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function normalizeNullableFloat(array $attributes, string $key): ?float
    {
        if (!array_key_exists($key, $attributes) || $attributes[$key] === null || $attributes[$key] === '') {
            return null;
        }

        return (float) $attributes[$key];
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function normalizeNullableString(array $attributes, string $key): ?string
    {
        if (!array_key_exists($key, $attributes)) {
            return null;
        }

        $value = trim((string) ($attributes[$key] ?? ''));

        return $value !== '' ? $value : null;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function normalizeBool(array $attributes, string $key, bool $default): bool
    {
        if (!array_key_exists($key, $attributes)) {
            return $default;
        }

        return filter_var($attributes[$key], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
    }
}

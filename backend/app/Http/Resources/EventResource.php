<?php

namespace App\Http\Resources;

use App\Services\Events\PublicConfidenceService;
use App\Services\Events\EventViewingRecommendationService;
use App\Support\EventTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $plan = $this->resolvePlanPayload();
        $recommendedViewing = app(EventViewingRecommendationService::class)->forEvent($this->resource);

        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type,
            'region_scope' => $this->region_scope,

            'start_at' => EventTime::serializeUtc($this->start_at),
            'end_at'   => EventTime::serializeUtc($this->end_at),
            'max_at'   => EventTime::serializeUtc($this->max_at),
            'starts_at' => EventTime::serializeUtc($this->start_at),
            'ends_at'   => EventTime::serializeUtc($this->end_at),
            'time_type' => EventTime::normalizeType($this->time_type, $this->start_at, $this->max_at),
            'time_precision' => EventTime::normalizePrecision(
                $this->time_precision,
                $this->start_at,
                $this->max_at,
                $this->source_name
            ),
            'all_day'   => (bool) ($this->all_day ?? false),

            'short' => $this->short,
            'description' => $this->description,
            'visibility' => (int) $this->visibility,

            // traceability (super pre BP)
            'source' => [
                'name' => $this->source_name,
                'uid'  => $this->source_uid,
                'hash' => $this->source_hash,
            ],
            'public_confidence' => app(PublicConfidenceService::class)->badgeFor($this->resource),
            'is_followed' => $this->when(
                $this->hasResourceAttribute('followed_at'),
                fn () => $this->resourceAttribute('followed_at') !== null
            ),
            'followed_at' => $this->when(
                isset($this->followed_at) && $this->followed_at !== null,
                fn () => $this->serializeDateLike($this->followed_at)
            ),
            'plan' => $this->when($plan !== null, $plan),
            'recommended_viewing_label' => $recommendedViewing['label'],
            'recommended_viewing_start_at' => $recommendedViewing['start_at'],
            'recommended_viewing_end_at' => $recommendedViewing['end_at'],

            'created_at' => EventTime::serializeUtc($this->created_at),
            'updated_at' => EventTime::serializeUtc($this->updated_at),
        ];
    }

    private function serializeDateLike(mixed $value): ?string
    {
        return EventTime::serializeUtc($value) ?? (is_string($value) && trim($value) !== '' ? $value : null);
    }

    /**
     * @return array<string,mixed>|null
     */
    private function resolvePlanPayload(): ?array
    {
        if (! $this->hasAnyPlanAttribute()) {
            return null;
        }

        $note = $this->toNullableString($this->resourceAttribute('personal_note'));
        $reminderAt = $this->serializeDateLike($this->resourceAttribute('reminder_at'));
        $plannedTime = $this->serializeDateLike($this->resourceAttribute('planned_time'));
        $location = $this->toNullableString($this->resourceAttribute('planned_location_label'));
        $hasData = $note !== null || $reminderAt !== null || $plannedTime !== null || $location !== null;

        return [
            'personal_note' => $note,
            'reminder_at' => $reminderAt,
            'planned_time' => $plannedTime,
            'planned_location_label' => $location,
            'has_personal_note' => $note !== null,
            'has_reminder' => $reminderAt !== null,
            'has_planned_time' => $plannedTime !== null,
            'has_planned_location' => $location !== null,
            'has_data' => $hasData,
        ];
    }

    private function hasAnyPlanAttribute(): bool
    {
        foreach (['personal_note', 'reminder_at', 'planned_time', 'planned_location_label'] as $attribute) {
            if ($this->hasResourceAttribute($attribute)) {
                return true;
            }
        }

        return false;
    }

    private function hasResourceAttribute(string $key): bool
    {
        if (is_array($this->resource)) {
            return array_key_exists($key, $this->resource);
        }

        if (is_object($this->resource) && method_exists($this->resource, 'getAttributes')) {
            /** @var array<string,mixed> $attributes */
            $attributes = $this->resource->getAttributes();

            return array_key_exists($key, $attributes);
        }

        return isset($this->{$key});
    }

    private function resourceAttribute(string $key): mixed
    {
        if (! $this->hasResourceAttribute($key)) {
            return null;
        }

        return $this->{$key};
    }

    private function toNullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}

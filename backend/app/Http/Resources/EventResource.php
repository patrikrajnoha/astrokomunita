<?php

namespace App\Http\Resources;

use App\Services\Events\PublicConfidenceService;
use App\Support\EventTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
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
            'followed_at' => $this->when(
                isset($this->followed_at) && $this->followed_at !== null,
                fn () => $this->serializeDateLike($this->followed_at)
            ),

            'created_at' => EventTime::serializeUtc($this->created_at),
            'updated_at' => EventTime::serializeUtc($this->updated_at),
        ];
    }

    private function serializeDateLike(mixed $value): ?string
    {
        return EventTime::serializeUtc($value) ?? (is_string($value) && trim($value) !== '' ? $value : null);
    }
}

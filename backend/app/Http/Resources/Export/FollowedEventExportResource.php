<?php

namespace App\Http\Resources\Export;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FollowedEventExportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'event' => [
                'id' => $this->event_id ?? $this->id,
                'title' => $this->event_title ?? $this->title,
                'type' => $this->event_type ?? $this->type,
                'starts_at' => $this->toIso8601($this->event_start_at ?? $this->start_at),
                'ends_at' => $this->toIso8601($this->event_end_at ?? $this->end_at),
                'source_name' => $this->event_source_name ?? $this->source_name,
                'source_uid' => $this->event_source_uid ?? $this->source_uid,
            ],
            'followed_at' => $this->toIso8601($this->followed_at ?? $this->created_at),
            'personal_plan' => [
                'note' => $this->personal_note ?? null,
                'reminder_at' => $this->toIso8601($this->reminder_at ?? null),
                'planned_time' => $this->toIso8601($this->planned_time ?? null),
                'planned_location_label' => $this->planned_location_label ?? null,
            ],
        ];
    }

    private function toIso8601(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse($value)->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }
}

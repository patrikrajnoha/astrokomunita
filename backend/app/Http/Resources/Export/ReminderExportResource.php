<?php

namespace App\Http\Resources\Export;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReminderExportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'event' => [
                'id' => $this->event?->id ?? $this->event_id,
                'title' => $this->event?->title,
                'starts_at' => optional($this->event?->start_at)?->toIso8601String(),
            ],
            'minutes_before' => (int) $this->minutes_before,
            'remind_at' => optional($this->remind_at)?->toIso8601String(),
            'status' => (string) $this->status,
            'sent_at' => optional($this->sent_at)?->toIso8601String(),
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }
}

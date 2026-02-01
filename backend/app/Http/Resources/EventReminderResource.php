<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventReminderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'event_id' => $this->event_id,
            'minutes_before' => $this->minutes_before,
            'remind_at' => optional($this->remind_at)?->toIso8601String(),
            'status' => $this->status,
            'sent_at' => optional($this->sent_at)?->toIso8601String(),
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
            'event' => $this->whenLoaded('event', function () {
                return [
                    'id' => $this->event?->id,
                    'title' => $this->event?->title,
                    'starts_at' => optional($this->event?->start_at)?->toIso8601String(),
                ];
            }),
        ];
    }
}

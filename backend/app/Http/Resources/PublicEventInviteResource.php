<?php

namespace App\Http\Resources;

use App\Enums\EventInviteStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicEventInviteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = $this->status;

        return [
            'id' => $this->id,
            'attendee_name' => $this->attendee_name,
            'status' => $status instanceof EventInviteStatus ? $status->value : $status,
            'responded_at' => optional($this->responded_at)?->toIso8601String(),
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'event' => $this->whenLoaded('event', function () {
                return [
                    'id' => $this->event?->id,
                    'title' => $this->event?->title,
                    'type' => $this->event?->type,
                    'start_at' => optional($this->event?->start_at)?->toIso8601String(),
                    'end_at' => optional($this->event?->end_at)?->toIso8601String(),
                    'max_at' => optional($this->event?->max_at)?->toIso8601String(),
                    'short' => $this->event?->short,
                ];
            }),
            'inviter' => $this->whenLoaded('inviter', function () {
                return [
                    'id' => $this->inviter?->id,
                    'name' => $this->inviter?->name,
                    'username' => $this->inviter?->username,
                ];
            }),
        ];
    }
}

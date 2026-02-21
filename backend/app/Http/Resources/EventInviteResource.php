<?php

namespace App\Http\Resources;

use App\Enums\EventInviteStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventInviteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = $this->status;

        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'inviter_user_id' => $this->inviter_user_id,
            'invitee_user_id' => $this->invitee_user_id,
            'invitee_email' => $this->invitee_email,
            'attendee_name' => $this->attendee_name,
            'message' => $this->message,
            'status' => $status instanceof EventInviteStatus ? $status->value : $status,
            'token' => $this->token,
            'responded_at' => optional($this->responded_at)?->toIso8601String(),
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
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
            'invitee' => $this->whenLoaded('invitee', function () {
                return [
                    'id' => $this->invitee?->id,
                    'name' => $this->invitee?->name,
                    'username' => $this->invitee?->username,
                ];
            }),
        ];
    }
}

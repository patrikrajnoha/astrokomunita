<?php

namespace App\Http\Resources\Export;

use App\Enums\EventInviteStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InviteExportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->status instanceof EventInviteStatus
            ? $this->status->value
            : (string) $this->status;

        return [
            'id' => $this->id,
            'event' => [
                'id' => $this->event?->id,
                'title' => $this->event?->title,
                'starts_at' => optional($this->event?->start_at)?->toIso8601String(),
                'location' => null,
            ],
            'status' => $status,
            'inviter_user_id' => $this->inviter_user_id,
            'invitee_email' => $this->invitee_email,
            'attendee_name' => $this->attendee_name,
            'message' => $this->message,
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'responded_at' => optional($this->responded_at)?->toIso8601String(),
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = $this->data ?? [];
        $createdAt = $this->created_at;

        return [
            'id' => $this->id,
            'type' => $this->type,
            'data' => $data,
            'read_at' => optional($this->read_at)?->toIso8601String(),
            'created_at' => optional($createdAt)?->toIso8601String(),
            'created_human' => $createdAt?->diffForHumans(),
            'target' => $this->resolveTarget($data),
        ];
    }

    private function resolveTarget(array $data): array
    {
        if ($this->type === 'post_liked') {
            $postId = $data['post_id'] ?? null;

            return [
                'kind' => 'post',
                'id' => $postId,
                'url' => $postId ? '/posts/' . $postId : null,
            ];
        }

        if ($this->type === 'event_reminder') {
            $eventId = $data['event_id'] ?? null;

            return [
                'kind' => 'event',
                'id' => $eventId,
                'url' => $eventId ? '/events/' . $eventId : null,
            ];
        }

        return [
            'kind' => Str::before($this->type, '_') ?: 'unknown',
            'id' => null,
            'url' => null,
        ];
    }
}

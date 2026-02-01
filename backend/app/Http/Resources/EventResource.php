<?php

namespace App\Http\Resources;

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

            'start_at' => optional($this->start_at)?->toIso8601String(),
            'end_at'   => optional($this->end_at)?->toIso8601String(),
            'max_at'   => optional($this->max_at)?->toIso8601String(),
            'starts_at' => optional($this->start_at)?->toIso8601String(),
            'ends_at'   => optional($this->end_at)?->toIso8601String(),
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

            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }
}

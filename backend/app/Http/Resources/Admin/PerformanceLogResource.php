<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PerformanceLogResource extends JsonResource
{
    /**
     * @return array<string,mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'key' => (string) $this->key,
            'environment' => (string) $this->environment,
            'sample_size' => (int) $this->sample_size,
            'duration_ms' => (int) $this->duration_ms,
            'avg_ms' => $this->avg_ms !== null ? (float) $this->avg_ms : null,
            'p95_ms' => $this->p95_ms !== null ? (float) $this->p95_ms : null,
            'min_ms' => (int) $this->min_ms,
            'max_ms' => (int) $this->max_ms,
            'db_queries_avg' => $this->db_queries_avg !== null ? (float) $this->db_queries_avg : null,
            'db_queries_p95' => $this->db_queries_p95 !== null ? (float) $this->db_queries_p95 : null,
            'payload' => is_array($this->payload) ? $this->payload : null,
            'created_by' => $this->created_by !== null ? (int) $this->created_by : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}


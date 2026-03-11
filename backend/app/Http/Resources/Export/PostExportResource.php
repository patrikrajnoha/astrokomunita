<?php

namespace App\Http\Resources\Export;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostExportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->content,
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
            'visibility' => $this->resolveVisibility(),
            'attachments' => $this->attachments(),
            'meta' => [
                'is_reply' => $this->parent_id !== null,
                'root_id' => $this->root_id,
                'source_name' => $this->source_name,
                'source_url' => $this->source_url,
                'source_published_at' => optional($this->source_published_at)?->toIso8601String(),
                'author_user_id' => $this->user_id,
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function attachments(): array
    {
        if (!$this->attachment_path || !$this->attachment_url) {
            return [];
        }

        return [[
            'type' => $this->resolveAttachmentType((string) $this->attachment_mime),
            'url' => $this->toAbsoluteUrl($this->attachment_url),
            'download_url' => $this->toAbsoluteUrl($this->attachment_download_url),
            'mime' => $this->attachment_mime,
            'width' => $this->attachment_width,
            'height' => $this->attachment_height,
            'size_bytes' => $this->attachment_size_web,
            'created_at' => optional($this->created_at)?->toIso8601String(),
        ]];
    }

    private function resolveVisibility(): string
    {
        if ($this->is_hidden || $this->hidden_at !== null || $this->moderation_status === 'blocked') {
            return 'hidden';
        }

        return 'public';
    }

    private function resolveAttachmentType(string $mime): string
    {
        $normalized = strtolower(trim($mime));

        if ($normalized === 'image/gif') {
            return 'gif';
        }

        if (str_starts_with($normalized, 'video/')) {
            return 'video';
        }

        return 'image';
    }

    private function toAbsoluteUrl(?string $path): ?string
    {
        $normalized = trim((string) $path);
        if ($normalized === '') {
            return null;
        }

        if (str_starts_with($normalized, 'http://') || str_starts_with($normalized, 'https://')) {
            return $normalized;
        }

        return url($normalized);
    }
}

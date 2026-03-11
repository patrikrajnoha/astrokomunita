<?php

namespace App\Http\Resources\Export;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class BookmarkExportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $postId = $this->post_id !== null ? (int) $this->post_id : null;
        $content = trim((string) ($this->post_content ?? ''));
        $isHidden = (bool) ($this->post_is_hidden ?? false);
        $isBlocked = (string) ($this->post_moderation_status ?? '') === 'blocked';
        $hiddenAt = $this->toIso8601($this->post_hidden_at ?? null);

        $post = null;
        if ($postId !== null) {
            $post = [
                'id' => $postId,
                'body_excerpt' => $content !== '' ? Str::limit($content, 180, '...') : null,
                'created_at' => $this->toIso8601($this->post_created_at ?? null),
                'visibility' => ($isHidden || $isBlocked || $hiddenAt !== null) ? 'hidden' : 'public',
            ];
        }

        return [
            'post_id' => $postId,
            'bookmarked_at' => $this->toIso8601($this->bookmarked_at ?? $this->created_at ?? null),
            'post' => $post,
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

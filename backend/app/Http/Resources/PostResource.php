<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'content' => $this->content,
            'views' => (int) $this->views,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'username' => $this->user->username,
                'email' => $this->user->email,
                'location' => $this->user->location,
                'bio' => $this->user->bio,
                'is_admin' => $this->user->is_admin,
                'avatar_path' => $this->user->avatar_path,
            ],
            'parent_id' => $this->parent_id,
            'parent' => $this->when($this->parent_id, function () {
                return [
                    'id' => $this->parent->id,
                    'user' => [
                        'id' => $this->parent->user->id,
                        'name' => $this->parent->user->name,
                        'username' => $this->parent->user->username,
                    ],
                ];
            }),
            'replies' => PostResource::collection($this->whenLoaded('replies')),
            'tags' => $this->whenLoaded('tags', function () {
                return $this->tags->map(fn ($tag) => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                ]);
            }),
            'attachment_path' => $this->attachment_path,
            'attachment_url' => $this->when($this->attachment_path, function () {
                return asset('storage/' . $this->attachment_path);
            }),
            'pinned_at' => $this->pinned_at,
            'is_hidden' => $this->is_hidden,
            'source_name' => $this->source_name,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'likes_count' => $this->when(isset($this->likes_count), $this->likes_count),
            'replies_count' => $this->when(isset($this->replies_count), $this->replies_count),
            'liked_by_me' => $this->when(isset($this->liked_by_me), (bool) $this->liked_by_me),
        ];
    }
}

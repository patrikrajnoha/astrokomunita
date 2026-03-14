<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    public function update(User $user, Post $post): bool
    {
        return (int) $post->user_id === (int) $user->id || (bool) $user->is_admin;
    }

    public function delete(User $user, Post $post): bool
    {
        return (int) $post->user_id === (int) $user->id || (bool) $user->is_admin;
    }

    public function viewRestricted(User $user, Post $post): bool
    {
        return $user->isAdmin();
    }

    public function downloadOriginal(User $user, Post $post): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return !$post->is_hidden
            && $post->hidden_at === null
            && $post->moderation_status !== 'blocked'
            && !$this->isAttachmentRestricted($post);
    }

    private function isAttachmentRestricted(Post $post): bool
    {
        $status = strtolower(trim((string) ($post->attachment_moderation_status ?? '')));

        return $post->attachment_hidden_at !== null
            || in_array($status, ['pending', 'flagged', 'blocked'], true);
    }
}

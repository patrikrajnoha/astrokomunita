<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

class PostPolicy
{
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
        if ((int) $post->user_id === (int) $user->id || $user->isAdmin()) {
            return true;
        }

        return !$post->is_hidden && $post->hidden_at === null && $post->moderation_status !== 'blocked';
    }
}

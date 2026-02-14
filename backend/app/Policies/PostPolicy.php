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
}

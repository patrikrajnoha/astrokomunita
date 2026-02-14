<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\Report;
use App\Models\User;

class ReportPolicy
{
    public function create(User $user, Post $targetPost): bool
    {
        return (int) $targetPost->user_id !== (int) $user->id;
    }

    public function review(User $user, Report $report): bool
    {
        return $user->isAdmin();
    }
}

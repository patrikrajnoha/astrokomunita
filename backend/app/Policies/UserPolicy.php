<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function ban(User $actor, User $target): bool
    {
        return $actor->isAdmin() && (int) $actor->id !== (int) $target->id;
    }

    public function unban(User $actor, User $target): bool
    {
        return $actor->isAdmin() && (int) $actor->id !== (int) $target->id;
    }

    public function deactivate(User $actor, User $target): bool
    {
        return $actor->isAdmin() && (int) $actor->id !== (int) $target->id;
    }

    public function resetProfile(User $actor, User $target): bool
    {
        return $actor->isAdmin() && (int) $actor->id !== (int) $target->id;
    }

    public function updateProfile(User $actor, User $target): bool
    {
        if (! $actor->isAdmin()) {
            return false;
        }

        if ($target->isBot() && ! $actor->isAdmin()) {
            return false;
        }

        return true;
    }

    public function updateRole(User $actor, User $target): bool
    {
        return $actor->isAdmin() && (int) $actor->id !== (int) $target->id;
    }
}

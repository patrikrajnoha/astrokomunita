<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    private function canManageAccountState(User $actor, User $target): bool
    {
        if (! $actor->isAdmin()) {
            return false;
        }

        if ((int) $actor->id === (int) $target->id) {
            return false;
        }

        return ! $target->isAdmin();
    }

    private function canManageBotProfile(User $actor, User $target): bool
    {
        return $this->canManageAccountState($actor, $target) && $target->isBot();
    }

    public function ban(User $actor, User $target): bool
    {
        return $this->canManageAccountState($actor, $target);
    }

    public function unban(User $actor, User $target): bool
    {
        return $this->canManageAccountState($actor, $target);
    }

    public function deactivate(User $actor, User $target): bool
    {
        return $this->canManageAccountState($actor, $target);
    }

    public function reactivate(User $actor, User $target): bool
    {
        return $this->canManageAccountState($actor, $target);
    }

    public function resetProfile(User $actor, User $target): bool
    {
        return $this->canManageAccountState($actor, $target);
    }

    public function updateProfile(User $actor, User $target): bool
    {
        return $this->canManageBotProfile($actor, $target);
    }

    public function updateRole(User $actor, User $target): bool
    {
        if (! $this->canManageAccountState($actor, $target)) {
            return false;
        }

        return ! $target->isBot();
    }
}

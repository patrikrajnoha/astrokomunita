<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function ban(User $actor, User $target): bool
    {
        return (int) $actor->id !== (int) $target->id;
    }

    public function unban(User $actor, User $target): bool
    {
        return (int) $actor->id !== (int) $target->id;
    }

    public function deactivate(User $actor, User $target): bool
    {
        return (int) $actor->id !== (int) $target->id;
    }

    public function resetProfile(User $actor, User $target): bool
    {
        return (int) $actor->id !== (int) $target->id;
    }
}

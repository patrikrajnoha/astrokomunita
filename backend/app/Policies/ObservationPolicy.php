<?php

namespace App\Policies;

use App\Models\Observation;
use App\Models\User;

class ObservationPolicy
{
    public function viewAny(?User $user = null): bool
    {
        return true;
    }

    public function view(?User $user, Observation $observation): bool
    {
        if ($observation->is_public) {
            return true;
        }

        return $this->isOwnerOrAdmin($user, $observation);
    }

    public function create(User $user): bool
    {
        return (bool) $user->id;
    }

    public function update(User $user, Observation $observation): bool
    {
        return $this->isOwnerOrAdmin($user, $observation);
    }

    public function delete(User $user, Observation $observation): bool
    {
        return $this->isOwnerOrAdmin($user, $observation);
    }

    public function viewPreciseLocation(?User $user, Observation $observation): bool
    {
        return $this->isOwnerOrAdmin($user, $observation);
    }

    private function isOwnerOrAdmin(?User $user, Observation $observation): bool
    {
        if (!$user) {
            return false;
        }

        return (int) $observation->user_id === (int) $user->id || $user->isAdmin();
    }
}

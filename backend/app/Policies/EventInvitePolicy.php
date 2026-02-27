<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\EventInvite;
use App\Models\User;

class EventInvitePolicy
{
    public function viewAny(User $user): bool
    {
        return (bool) $user->id;
    }

    public function create(User $user, Event $event): bool
    {
        return (bool) $user->id && (bool) $event->id;
    }

    public function view(User $user, EventInvite $invite): bool
    {
        if ((int) $invite->inviter_user_id === (int) $user->id) {
            return true;
        }

        if ((int) ($invite->invitee_user_id ?? 0) === (int) $user->id) {
            return true;
        }

        return strcasecmp((string) ($invite->invitee_email ?? ''), (string) ($user->email ?? '')) === 0;
    }

    public function respond(User $user, EventInvite $invite): bool
    {
        if ((int) ($invite->invitee_user_id ?? 0) === (int) $user->id) {
            return true;
        }

        return strcasecmp((string) ($invite->invitee_email ?? ''), (string) ($user->email ?? '')) === 0;
    }
}

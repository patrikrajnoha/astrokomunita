<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('users.{id}', static function ($user, $id): bool {
    return (int) $user->id === (int) $id;
});
Broadcast::channel('events.feed', static fn (): bool => true);

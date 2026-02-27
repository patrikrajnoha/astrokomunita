<?php

use Illuminate\Support\Facades\Broadcast;

$authorizeUserChannel = static function ($user, $id): bool {
    $resolvedId = (int) preg_replace('/\D+/', '', (string) $id);

    return (int) $user->id === $resolvedId;
};

Broadcast::channel('users.{id}', $authorizeUserChannel);
Broadcast::channel('private-users.{id}', $authorizeUserChannel);
Broadcast::channel('events.feed', static fn (): bool => true);

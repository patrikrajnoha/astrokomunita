<?php

namespace App\Enums;

enum EventInviteStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Revoked = 'revoked';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $status) => $status->value, self::cases());
    }
}

<?php

namespace App\Models;

use App\Enums\EventInviteStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventInvite extends Model
{
    protected $fillable = [
        'event_id',
        'inviter_user_id',
        'invitee_user_id',
        'invitee_email',
        'attendee_name',
        'message',
        'status',
        'token',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'event_id' => 'integer',
            'inviter_user_id' => 'integer',
            'invitee_user_id' => 'integer',
            'status' => EventInviteStatus::class,
            'responded_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inviter_user_id');
    }

    public function invitee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invitee_user_id');
    }
}

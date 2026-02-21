<?php

namespace App\Services;

use App\Enums\EventInviteStatus;
use App\Models\Event;
use App\Models\EventInvite;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EventInviteService
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly UserActivityService $activityService,
    ) {
    }

    public function createInvite(User $inviter, Event $event, array $payload): EventInvite
    {
        return DB::transaction(function () use ($inviter, $event, $payload) {
            $inviteeUserId = isset($payload['invitee_user_id']) ? (int) $payload['invitee_user_id'] : null;
            $inviteeEmail = isset($payload['invitee_email']) ? strtolower(trim((string) $payload['invitee_email'])) : null;

            if ($inviteeUserId) {
                $invitee = User::query()->select(['id', 'email'])->find($inviteeUserId);
                if ($invitee) {
                    $inviteeEmail = strtolower((string) $invitee->email);
                }
            }

            if ($inviteeUserId && $inviteeUserId === (int) $inviter->id) {
                throw ValidationException::withMessages([
                    'invitee_user_id' => ['Nemozes pozvat sam seba.'],
                ]);
            }

            if ($inviteeEmail && strcasecmp($inviteeEmail, (string) $inviter->email) === 0) {
                throw ValidationException::withMessages([
                    'invitee_email' => ['Nemozes pozvat sam seba.'],
                ]);
            }

            $invite = EventInvite::query()->create([
                'event_id' => $event->id,
                'inviter_user_id' => $inviter->id,
                'invitee_user_id' => $inviteeUserId,
                'invitee_email' => $inviteeEmail,
                'attendee_name' => trim((string) $payload['attendee_name']),
                'message' => isset($payload['message']) ? trim((string) $payload['message']) : null,
                'status' => EventInviteStatus::Pending,
                'token' => $this->generateToken(),
            ]);

            if ($invite->invitee_user_id) {
                $this->notifications->createEventInvite(
                    (int) $invite->invitee_user_id,
                    (int) $inviter->id,
                    (int) $event->id,
                    (string) $event->title,
                    [
                        'invite_id' => $invite->id,
                        'invite_status' => EventInviteStatus::Pending->value,
                        'invite_token' => $invite->token,
                        'attendee_name' => $invite->attendee_name,
                    ],
                );
            }

            return $invite->load(['event', 'inviter', 'invitee']);
        });
    }

    public function listForUser(User $user, ?string $status = null): Collection
    {
        $query = EventInvite::query()
            ->where(function ($builder) use ($user) {
                $builder->where('invitee_user_id', $user->id);

                if (!empty($user->email)) {
                    $builder->orWhereRaw('LOWER(invitee_email) = ?', [strtolower((string) $user->email)]);
                }
            })
            ->with(['event', 'inviter', 'invitee'])
            ->orderByDesc('created_at');

        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        }

        return $query->get();
    }

    public function respond(User $user, EventInvite $invite, EventInviteStatus $status): EventInvite
    {
        if (!in_array($status, [EventInviteStatus::Accepted, EventInviteStatus::Declined], true)) {
            throw ValidationException::withMessages([
                'status' => ['Neplatny stav odpovede pozvanky.'],
            ]);
        }

        return DB::transaction(function () use ($user, $invite, $status) {
            $fresh = EventInvite::query()->lockForUpdate()->findOrFail($invite->id);
            $currentStatus = $fresh->status instanceof EventInviteStatus
                ? $fresh->status
                : EventInviteStatus::tryFrom((string) $fresh->status);

            if ($currentStatus && $currentStatus !== EventInviteStatus::Pending) {
                throw ValidationException::withMessages([
                    'status' => ['Na tuto pozvanku uz bolo odpovedane.'],
                ]);
            }

            $fresh->status = $status;
            $fresh->responded_at = now();
            $fresh->save();

            if ((int) $fresh->inviter_user_id !== (int) $user->id) {
                $this->notifications->createEventInviteResponse(
                    (int) $fresh->inviter_user_id,
                    (int) $user->id,
                    (int) $fresh->event_id,
                    (string) optional($fresh->event)->title,
                    $status->value,
                    [
                        'invite_id' => $fresh->id,
                        'attendee_name' => $fresh->attendee_name,
                    ],
                );
            }

            if ($status === EventInviteStatus::Accepted) {
                DB::afterCommit(fn () => $this->activityService->forgetActivity($user));
            }

            return $fresh->load(['event', 'inviter', 'invitee']);
        });
    }

    public function findPublicByToken(string $token): ?EventInvite
    {
        $normalized = trim($token);
        if ($normalized === '') {
            return null;
        }

        return EventInvite::query()
            ->where('token', $normalized)
            ->whereIn('status', [
                EventInviteStatus::Pending->value,
                EventInviteStatus::Accepted->value,
                EventInviteStatus::Declined->value,
            ])
            ->with(['event', 'inviter', 'invitee'])
            ->first();
    }

    private function generateToken(): string
    {
        do {
            $token = Str::random(64);
        } while (EventInvite::query()->where('token', $token)->exists());

        return $token;
    }
}

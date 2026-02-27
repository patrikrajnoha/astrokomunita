<?php

namespace App\Http\Controllers\Api;

use App\Enums\EventInviteStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\EventInviteResource;
use App\Http\Resources\PublicEventInviteResource;
use App\Models\Event;
use App\Models\EventInvite;
use App\Services\EventInviteService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EventInviteController extends Controller
{
    public function __construct(private readonly EventInviteService $service)
    {
    }

    /**
     * POST /api/events/{event}/invites
     */
    public function store(Request $request, Event $event)
    {
        $this->authorize('create', [EventInvite::class, $event]);

        $validated = $request->validate([
            'invitee_user_id' => ['nullable', 'integer', 'exists:users,id', 'required_without:invitee_email'],
            'invitee_email' => ['nullable', 'string', 'email:rfc', 'max:255', 'required_without:invitee_user_id'],
            'attendee_name' => ['required', 'string', 'max:80'],
            'message' => ['nullable', 'string', 'max:240'],
        ]);

        $invite = $this->service->createInvite($request->user(), $event, $validated);

        return (new EventInviteResource($invite))
            ->response()
            ->setStatusCode(200);
    }

    /**
     * GET /api/me/invites
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', EventInvite::class);

        $validated = $request->validate([
            'status' => ['nullable', Rule::in([
                EventInviteStatus::Pending->value,
                EventInviteStatus::Accepted->value,
                EventInviteStatus::Declined->value,
            ])],
        ]);

        $items = $this->service->listForUser(
            $request->user(),
            isset($validated['status']) ? (string) $validated['status'] : null,
        );

        return EventInviteResource::collection($items);
    }

    /**
     * POST /api/invites/{invite}/accept
     */
    public function accept(Request $request, EventInvite $invite)
    {
        $this->authorize('respond', $invite);

        $updated = $this->service->respond($request->user(), $invite, EventInviteStatus::Accepted);

        return new EventInviteResource($updated);
    }

    /**
     * POST /api/invites/{invite}/decline
     */
    public function decline(Request $request, EventInvite $invite)
    {
        $this->authorize('respond', $invite);

        $updated = $this->service->respond($request->user(), $invite, EventInviteStatus::Declined);

        return new EventInviteResource($updated);
    }

    /**
     * GET /api/invites/public/{token}
     */
    public function publicShow(string $token)
    {
        $invite = $this->service->findPublicByToken($token);
        abort_unless($invite, 404);

        return new PublicEventInviteResource($invite);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notifications)
    {
    }

    /**
     * GET /api/notifications
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $items = $this->notifications->list(
            $request->user()->id,
            (int) ($validated['per_page'] ?? 20)
        );

        return NotificationResource::collection($items);
    }

    /**
     * GET /api/notifications/unread-count
     */
    public function unreadCount(Request $request)
    {
        return response()->json([
            'count' => $this->notifications->unreadCount($request->user()->id),
        ]);
    }

    /**
     * POST /api/notifications/{id}/read
     */
    public function markRead(Request $request, int $id)
    {
        try {
            $notification = $this->notifications->markRead($id, $request->user()->id);
        } catch (\Throwable) {
            throw new NotFoundHttpException('Notification not found');
        }

        return new NotificationResource($notification);
    }

    /**
     * POST /api/notifications/read-all
     */
    public function markAllRead(Request $request)
    {
        $count = $this->notifications->markAllRead($request->user()->id);

        return response()->json(['updated' => $count]);
    }

    /**
     * POST /api/notifications/dev-test
     */
    public function devTest(Request $request)
    {
        abort_unless(app()->environment('local') && config('app.debug'), 404);

        $validated = $request->validate([
            'type' => ['nullable', Rule::in(['event_invite', 'contest_winner', 'account_restricted'])],
            'recipient_id' => ['nullable', 'integer', 'exists:users,id'],
            'event_id' => ['nullable', 'integer'],
            'event_title' => ['nullable', 'string', 'max:255'],
            'contest_id' => ['nullable', 'integer', 'required_if:type,contest_winner'],
            'contest_name' => ['nullable', 'string', 'max:255'],
            'post_id' => ['nullable', 'integer', 'required_if:type,contest_winner'],
            'reason' => ['nullable', 'string', 'max:1000', 'required_if:type,account_restricted'],
        ]);

        $actorId = (int) $request->user()->id;
        $recipientId = (int) ($validated['recipient_id'] ?? $actorId);
        $type = (string) ($validated['type'] ?? 'event_invite');

        $notification = match ($type) {
            'contest_winner' => $this->notifications->createContestWinner(
                $recipientId,
                (int) $validated['contest_id'],
                (string) ($validated['contest_name'] ?? 'Contest'),
                (int) $validated['post_id'],
            ),
            'account_restricted' => $this->notifications->createAccountRestricted(
                $recipientId,
                (string) $validated['reason'],
                $actorId,
            ),
            default => $this->notifications->createEventInvite(
                $recipientId,
                $actorId,
                isset($validated['event_id']) ? (int) $validated['event_id'] : null,
                $validated['event_title'] ?? null,
            ),
        };

        if (!$notification) {
            return response()->json(['message' => 'Notification was not created.'], 422);
        }

        return new NotificationResource($notification);
    }
}

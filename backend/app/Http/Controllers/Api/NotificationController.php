<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Services\NotificationService;
use Illuminate\Http\Request;
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
}

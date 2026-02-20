<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\NewsletterRun;
use App\Models\User;
use App\Services\Newsletter\NewsletterDispatchService;
use App\Services\Newsletter\NewsletterSelectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminNewsletterController extends Controller
{
    public function __construct(
        private readonly NewsletterSelectionService $selectionService,
        private readonly NewsletterDispatchService $dispatchService,
    ) {
    }

    public function preview(): JsonResponse
    {
        return response()->json([
            'data' => $this->selectionService->buildNewsletterPayload(),
            'meta' => [
                'max_featured_events' => NewsletterSelectionService::MAX_FEATURED_EVENTS,
            ],
        ]);
    }

    public function featureEvents(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_ids' => ['required', 'array', 'max:' . NewsletterSelectionService::MAX_FEATURED_EVENTS],
            'event_ids.*' => ['integer', 'distinct'],
        ]);

        $items = $this->selectionService->replaceAdminSelectedEvents($validated['event_ids']);

        return response()->json([
            'data' => $items,
            'meta' => [
                'count' => count($items),
                'max_featured_events' => NewsletterSelectionService::MAX_FEATURED_EVENTS,
            ],
        ]);
    }

    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'force' => ['nullable', 'boolean'],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        $result = $this->dispatchService->dispatchWeeklyNewsletter(
            adminUser: $request->user(),
            forced: (bool) ($validated['force'] ?? false),
            dryRun: (bool) ($validated['dry_run'] ?? false),
        );

        $run = $result['run'];
        $statusCode = $result['created'] ? 202 : 200;

        return response()->json([
            'created' => $result['created'],
            'reason' => $result['reason'],
            'data' => $run ? $this->mapRun($run) : null,
        ], $statusCode);
    }

    public function sendPreview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $targetEmail = mb_strtolower(trim((string) $validated['email']));
        $targetUser = User::query()
            ->whereRaw('LOWER(email) = ?', [$targetEmail])
            ->first();

        if (! $targetUser) {
            throw ValidationException::withMessages([
                'email' => ['No user with this email exists.'],
            ]);
        }

        $payload = $this->selectionService->buildNewsletterPayload();
        $sent = $this->dispatchService->sendPreviewToUser($targetUser, $payload);

        if (! $sent) {
            return response()->json([
                'ok' => false,
                'message' => 'Preview email could not be sent.',
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'data' => [
                'email' => $targetUser->email,
                'events_count' => count((array) ($payload['top_events'] ?? [])),
                'articles_count' => count((array) ($payload['top_articles'] ?? [])),
            ],
        ], 202);
    }

    public function runs(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 20);
        $paginator = $this->dispatchService->listRuns($perPage);

        return response()->json([
            'data' => $paginator->getCollection()
                ->map(fn (NewsletterRun $run): array => $this->mapRun($run))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapRun(NewsletterRun $run): array
    {
        return [
            'id' => (int) $run->id,
            'week_start_date' => optional($run->week_start_date)->toDateString(),
            'status' => (string) $run->status,
            'total_recipients' => (int) $run->total_recipients,
            'sent_count' => (int) $run->sent_count,
            'failed_count' => (int) $run->failed_count,
            'forced' => (bool) $run->forced,
            'dry_run' => (bool) $run->dry_run,
            'error' => $run->error,
            'admin_user' => $run->adminUser ? [
                'id' => (int) $run->adminUser->id,
                'name' => (string) $run->adminUser->name,
                'email' => (string) $run->adminUser->email,
            ] : null,
            'started_at' => optional($run->started_at)->toIso8601String(),
            'finished_at' => optional($run->finished_at)->toIso8601String(),
            'created_at' => optional($run->created_at)->toIso8601String(),
            'updated_at' => optional($run->updated_at)->toIso8601String(),
        ];
    }
}

<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\NewsletterRun;
use App\Models\User;
use App\Services\Newsletter\NewsletterDispatchService;
use App\Services\Newsletter\NewsletterSelectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminNewsletterController extends Controller
{
    private const ISO_TIMESTAMP_PATTERN = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2})?(?:\.\d{1,6})?(?:Z|[+\-]\d{2}:\d{2})?$/u';

    public function __construct(
        private readonly NewsletterSelectionService $selectionService,
        private readonly NewsletterDispatchService $dispatchService,
    ) {
    }

    public function preview(): JsonResponse
    {
        return response()->json([
            'data' => $this->selectionService->buildNewsletterPayload(adminPreview: true),
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
            'subject_override' => ['sometimes', 'nullable', 'string'],
            'intro_override' => ['sometimes', 'nullable', 'string'],
            'tip_override' => ['sometimes', 'nullable', 'string'],
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

        $sanitized = $this->sanitizePreviewOverrides($validated);
        $overrides = $sanitized['overrides'];
        $warnings = $sanitized['warnings'];
        $payload = $this->selectionService->buildNewsletterPayload(adminPreview: true);
        if (isset($overrides['subject_override'])) {
            $payload['subject_override'] = $overrides['subject_override'];
        }
        if (isset($overrides['intro_override'])) {
            $payload['intro_override'] = $overrides['intro_override'];
        }
        if (isset($overrides['tip_override'])) {
            $payload['astronomical_tip'] = $overrides['tip_override'];
        }

        $sent = $this->dispatchService->sendPreviewToUser($targetUser, $payload);

        if (! $sent) {
            return response()->json([
                'ok' => false,
                'message' => 'E-mail s nahladom sa nepodarilo odoslat.',
            ], 422);
        }

        $previewRun = $this->dispatchService->recordPreviewDispatch($request->user());

        return response()->json([
            'ok' => true,
            'data' => [
                'email' => $targetUser->email,
                'events_count' => count((array) ($payload['top_events'] ?? [])),
                'articles_count' => count((array) ($payload['top_articles'] ?? [])),
                'run_id' => (int) $previewRun->id,
                'preview_count' => (int) $previewRun->preview_count,
            ],
            'warnings' => $warnings,
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
            'preview_count' => (int) $run->preview_count,
            'unsubscribe_count' => (int) $run->unsubscribe_count,
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

    /**
     * @param array<string,mixed> $validated
     * @return array{overrides:array<string,string>,warnings:array<int,string>}
     */
    private function sanitizePreviewOverrides(array $validated): array
    {
        $limits = [
            'subject_override' => 80,
            'intro_override' => 280,
            'tip_override' => 320,
        ];

        $normalized = [];
        $warnings = [];

        foreach ($limits as $field => $maxLength) {
            if (! array_key_exists($field, $validated)) {
                continue;
            }

            if ($validated[$field] === null) {
                $warnings[] = $this->warningMessage($field, 'ignored: empty after sanitization, fallback applied.');
                continue;
            }

            $value = $this->sanitizePreviewText((string) $validated[$field]);
            if ($value === '') {
                $warnings[] = $this->warningMessage($field, 'ignored: empty after sanitization, fallback applied.');
                continue;
            }

            if ($this->containsIsoTimestamp($value)) {
                $warnings[] = $this->warningMessage($field, 'ignored: ISO timestamp-only value is not allowed, fallback applied.');
                continue;
            }

            $normalized[$field] = $this->limitTextLength($value, $maxLength);
        }

        return [
            'overrides' => $normalized,
            'warnings' => $warnings,
        ];
    }

    private function sanitizePreviewText(string $value): string
    {
        $plain = trim(strip_tags($value));
        if ($plain === '') {
            return '';
        }

        $plain = preg_replace('/\s+/u', ' ', $plain) ?? $plain;
        return trim($plain);
    }

    private function containsIsoTimestamp(string $value): bool
    {
        return preg_match(self::ISO_TIMESTAMP_PATTERN, trim($value)) === 1;
    }

    private function limitTextLength(string $value, int $maxLength): string
    {
        return Str::limit(trim($value), max(1, $maxLength), '');
    }

    private function warningMessage(string $field, string $message): string
    {
        return $field . ' ' . $message;
    }
}


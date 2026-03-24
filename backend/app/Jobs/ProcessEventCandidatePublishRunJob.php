<?php

namespace App\Jobs;

use App\Models\EventCandidatePublishRun;
use App\Services\Events\EventCandidateBatchPublisher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessEventCandidatePublishRunJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(
        public readonly int $runId
    ) {
    }

    public function handle(EventCandidateBatchPublisher $batchPublisher): void
    {
        $run = EventCandidatePublishRun::query()->find($this->runId);
        if (! $run || $run->isTerminal()) {
            return;
        }

        try {
            $candidateIds = $this->extractCandidateIds($run);
            $totalSelected = count($candidateIds);

            $run->forceFill([
                'status' => EventCandidatePublishRun::STATUS_RUNNING,
                'started_at' => $run->started_at ?? now(),
                'finished_at' => null,
                'total_selected' => $totalSelected,
                'processed' => 0,
                'published' => 0,
                'failed' => 0,
                'error_message' => null,
            ])->save();

            if ($candidateIds === []) {
                $run->forceFill([
                    'status' => EventCandidatePublishRun::STATUS_COMPLETED,
                    'finished_at' => now(),
                ])->save();

                return;
            }

            $published = 0;
            $failed = 0;
            $processed = 0;

            $reviewerId = max(1, (int) $run->reviewer_user_id);
            $publishMode = $this->normalizePublishMode($run->publish_generation_mode);

            foreach ($candidateIds as $candidateId) {
                try {
                    $wasPublished = $batchPublisher->approvePendingCandidate(
                        candidateId: $candidateId,
                        reviewerUserId: $reviewerId,
                        publishGenerationMode: $publishMode
                    );

                    if ($wasPublished) {
                        $published++;
                    } else {
                        $failed++;
                    }
                } catch (\Throwable $exception) {
                    $failed++;
                    Log::warning('Event candidate publish run item failed', [
                        'run_id' => (int) $run->id,
                        'candidate_id' => $candidateId,
                        'publish_mode' => $publishMode,
                        'error' => $exception->getMessage(),
                    ]);
                }

                $processed++;
                $run->forceFill([
                    'processed' => $processed,
                    'published' => $published,
                    'failed' => $failed,
                ])->save();
            }

            $run->forceFill([
                'status' => $failed > 0
                    ? EventCandidatePublishRun::STATUS_COMPLETED_WITH_FAILURES
                    : EventCandidatePublishRun::STATUS_COMPLETED,
                'finished_at' => now(),
            ])->save();
        } catch (\Throwable $exception) {
            Log::warning('Event candidate publish run failed', [
                'run_id' => $this->runId,
                'error' => $exception->getMessage(),
            ]);

            if ($run instanceof EventCandidatePublishRun) {
                $run->forceFill([
                    'status' => EventCandidatePublishRun::STATUS_FAILED,
                    'finished_at' => now(),
                    'error_message' => mb_substr((string) $exception->getMessage(), 0, 5000),
                ])->save();
            }
        }
    }

    /**
     * @return array<int,int>
     */
    private function extractCandidateIds(EventCandidatePublishRun $run): array
    {
        $rawIds = data_get($run->meta, 'candidate_ids', []);
        if (! is_array($rawIds)) {
            return [];
        }

        $ids = [];
        foreach ($rawIds as $rawId) {
            $candidateId = (int) $rawId;
            if ($candidateId <= 0) {
                continue;
            }

            $ids[$candidateId] = $candidateId;
        }

        return array_values($ids);
    }

    private function normalizePublishMode(mixed $value): string
    {
        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['template', 'ai', 'mix'], true) ? $normalized : 'template';
    }
}

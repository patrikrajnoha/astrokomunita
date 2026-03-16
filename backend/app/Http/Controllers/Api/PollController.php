<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Poll\VotePollRequest;
use App\Models\Poll;
use App\Models\PollVote;
use App\Services\PollService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PollController extends Controller
{
    public function __construct(
        private readonly PollService $polls,
    ) {
    }

    public function show(Request $request, Poll $poll)
    {
        $viewer = $request->user() ?? $request->user('sanctum');
        $poll->load(array_merge(['options'], $viewer ? [
            'pollVotes' => fn ($query) => $query->where('user_id', $viewer->id),
        ] : []));

        return response()->json($this->polls->toPayload($poll, $viewer?->id));
    }

    public function vote(VotePollRequest $request, Poll $poll)
    {
        $user = $request->user();
        $optionId = (int) $request->validated('option_id');

        $poll->load('options');

        if (now()->greaterThanOrEqualTo($poll->ends_at)) {
            return response()->json([
                'message' => 'Anketa je uz uzavreta.',
            ], 422);
        }

        $option = $poll->options->firstWhere('id', $optionId);
        if (!$option) {
            return response()->json([
                'message' => 'Neplatná možnosť ankety.',
            ], 422);
        }

        try {
            DB::transaction(function () use ($poll, $option, $user) {
                PollVote::create([
                    'poll_id' => $poll->id,
                    'poll_option_id' => $option->id,
                    'user_id' => $user->id,
                    'created_at' => now(),
                ]);

                $option->increment('votes_count');
            });
        } catch (QueryException $exception) {
            if ($this->isUniqueVoteViolation($exception)) {
                return response()->json([
                    'message' => 'V tejto ankete ste uz hlasovali.',
                ], 409);
            }

            throw $exception;
        }

        $poll->load(array_merge(['options'], [
            'pollVotes' => fn ($query) => $query->where('user_id', $user->id),
        ]));

        return response()->json($this->polls->toPayload($poll, $user?->id));
    }

    private function isUniqueVoteViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        $driverCode = (string) ($exception->errorInfo[1] ?? '');
        $message = strtolower((string) ($exception->errorInfo[2] ?? $exception->getMessage()));

        if ($sqlState === '23000') {
            return true;
        }

        if ($driverCode === '19') {
            return true;
        }

        return str_contains($message, 'poll_votes.poll_id') && str_contains($message, 'poll_votes.user_id');
    }
}


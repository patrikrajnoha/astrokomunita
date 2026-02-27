<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Newsletter\NewsletterDispatchService;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;

class NewsletterUnsubscribeController extends Controller
{
    public function __construct(
        private readonly NewsletterDispatchService $dispatchService,
    ) {
    }

    public function __invoke(Request $request, User $user): View
    {
        $wasSubscribed = (bool) $user->newsletter_subscribed;

        if ($wasSubscribed) {
            $user->forceFill([
                'newsletter_subscribed' => false,
            ])->save();

            $runId = (int) $request->query('run', 0);
            if ($runId > 0) {
                $this->dispatchService->incrementUnsubscribeCount($runId);
            }
        }

        return view('newsletter.unsubscribe', [
            'alreadyUnsubscribed' => ! $wasSubscribed,
        ]);
    }
}

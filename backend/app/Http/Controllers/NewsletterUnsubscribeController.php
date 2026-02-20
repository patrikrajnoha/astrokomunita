<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;

class NewsletterUnsubscribeController extends Controller
{
    public function __invoke(User $user): View
    {
        $wasSubscribed = (bool) $user->newsletter_subscribed;

        if ($wasSubscribed) {
            $user->forceFill([
                'newsletter_subscribed' => false,
            ])->save();
        }

        return view('newsletter.unsubscribe', [
            'alreadyUnsubscribed' => ! $wasSubscribed,
        ]);
    }
}


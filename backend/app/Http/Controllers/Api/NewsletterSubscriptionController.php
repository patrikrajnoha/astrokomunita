<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewsletterSubscriptionController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'newsletter_subscribed' => ['required', 'boolean'],
        ]);

        $user = $request->user();
        $user->forceFill([
            'newsletter_subscribed' => (bool) $validated['newsletter_subscribed'],
        ])->save();

        return response()->json([
            'data' => [
                'newsletter_subscribed' => (bool) $user->newsletter_subscribed,
            ],
        ]);
    }
}

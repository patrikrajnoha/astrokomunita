<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\UserActivityService;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Schema;

class UpdateLastLoginListener
{
    public function __construct(
        private readonly UserActivityService $activityService,
    ) {
    }

    public function handle(Login $event): void
    {
        if (!($event->user instanceof User)) {
            return;
        }

        if (!Schema::hasColumn('users', 'last_login_at')) {
            return;
        }

        $event->user->forceFill([
            'last_login_at' => now(),
        ])->saveQuietly();

        $this->activityService->forgetActivity($event->user);
    }
}

<?php

namespace App\Providers;

use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use App\Models\Observation;
use App\Models\EventInvite;
use App\Policies\PostPolicy;
use App\Policies\ReportPolicy;
use App\Policies\UserPolicy;
use App\Policies\ObservationPolicy;
use App\Policies\EventInvitePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Post::class => PostPolicy::class,
        Report::class => ReportPolicy::class,
        User::class => UserPolicy::class,
        Observation::class => ObservationPolicy::class,
        EventInvite::class => EventInvitePolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        Gate::policy(Post::class, PostPolicy::class);
        Gate::policy(Report::class, ReportPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Observation::class, ObservationPolicy::class);
        Gate::policy(EventInvite::class, EventInvitePolicy::class);
    }
}

<?php

namespace App\Providers;

use App\Console\Commands\ImportEventCandidates;
use App\Console\Commands\ImportNasaNewsCommand;
use App\Console\Commands\SendEventReminders;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->commands([
            ImportEventCandidates::class,
            ImportNasaNewsCommand::class,
            SendEventReminders::class,
        ]);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}

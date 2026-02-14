<?php

namespace App\Providers;

use App\Console\Commands\ImportEventCandidates;
use App\Console\Commands\ImportNasaNewsCommand;
use App\Console\Commands\SendEventReminders;
use App\Console\Commands\SendEventNotificationReminders;
use App\Console\Commands\AstroBotSyncRss;
use App\Services\Observing\Contracts\AirQualityProvider;
use App\Services\Observing\Contracts\SunMoonProvider;
use App\Services\Observing\Contracts\WeatherProvider;
use App\Services\Observing\Providers\OpenAqAirQualityProvider;
use App\Services\Observing\Providers\OpenMeteoWeatherProvider;
use App\Services\Observing\Providers\UsnoSunMoonProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SunMoonProvider::class, UsnoSunMoonProvider::class);
        $this->app->bind(WeatherProvider::class, OpenMeteoWeatherProvider::class);
        $this->app->bind(AirQualityProvider::class, OpenAqAirQualityProvider::class);

        $this->commands([
            ImportEventCandidates::class,
            ImportNasaNewsCommand::class,
            SendEventReminders::class,
            SendEventNotificationReminders::class,
            AstroBotSyncRss::class,
        ]);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('auth-register', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip() . '|register');
        });

        RateLimiter::for('auth-login', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip() . '|login');
        });

        RateLimiter::for('auth-username-available', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip() . '|username-available');
        });

        RateLimiter::for('astrobot-sync', function (Request $request) {
            $userId = $request->user()?->id ?? $request->ip();
            return Limit::perMinute(2)->by('astrobot-sync|' . $userId);
        });

        RateLimiter::for('post-create', function (Request $request) {
            $userId = $request->user()?->id ?? 'guest';
            return Limit::perMinute(20)->by('post-create|' . $userId . '|' . $request->ip());
        });
    }
}

<?php

namespace App\Providers;

use App\Console\Commands\ImportEventCandidates;
use App\Console\Commands\ImportNasaNewsCommand;
use App\Console\Commands\CrawlAstropixelsEventsCommand;
use App\Console\Commands\RunBotSourceCommand;
use App\Console\Commands\SendEventReminders;
use App\Console\Commands\SendEventNotificationReminders;
use App\Console\Commands\SendWeeklyNewsletterCommand;
use App\Console\Commands\AstroBotSyncRss;
use App\Console\Commands\GenerateEventDescriptionsCommand;
use App\Listeners\UpdateLastLoginListener;
use App\Support\Http\SslVerificationPolicy;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use App\Services\Observing\Contracts\AirQualityProvider;
use App\Services\Observing\Contracts\SunMoonProvider;
use App\Services\Observing\Contracts\WeatherProvider;
use App\Services\Bots\Contracts\BotTranslationServiceInterface;
use App\Services\Bots\DummyBotTranslationService;
use App\Services\Bots\HttpBotTranslationService;
use App\Services\Observing\Providers\OpenAqAirQualityProvider;
use App\Services\Observing\Providers\OpenMeteoWeatherProvider;
use App\Services\Observing\Providers\UsnoSunMoonProvider;
use App\Services\Translation\Grammar\Contracts\GrammarCheckerInterface;
use App\Services\Translation\Grammar\LanguageToolGrammarChecker;
use App\Services\Translation\Grammar\OllamaGrammarChecker;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
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
        $this->app->bind(BotTranslationServiceInterface::class, function ($app) {
            $provider = strtolower(trim((string) config('astrobot.translation_provider', 'dummy')));

            return match ($provider) {
                'http' => $app->make(HttpBotTranslationService::class),
                default => $app->make(DummyBotTranslationService::class),
            };
        });
        $this->app->bind(GrammarCheckerInterface::class, function ($app) {
            $provider = strtolower(trim((string) config('translation.grammar.provider', 'languagetool')));

            return match ($provider) {
                'ollama' => $app->make(OllamaGrammarChecker::class),
                default => $app->make(LanguageToolGrammarChecker::class),
            };
        });

        $this->commands([
            ImportEventCandidates::class,
            ImportNasaNewsCommand::class,
            CrawlAstropixelsEventsCommand::class,
            RunBotSourceCommand::class,
            SendEventReminders::class,
            SendEventNotificationReminders::class,
            SendWeeklyNewsletterCommand::class,
            AstroBotSyncRss::class,
            GenerateEventDescriptionsCommand::class,
        ]);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(Login::class, UpdateLastLoginListener::class);

        Http::macro('secure', function (): PendingRequest {
            /** @var HttpFactory $this */
            $verifySsl = app(SslVerificationPolicy::class)->shouldVerifySsl();

            return $this->withOptions([
                'verify' => $verifySsl,
            ]);
        });

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

        RateLimiter::for('gif-search', function (Request $request) {
            $userId = $request->user()?->id ?? 'guest';
            return Limit::perMinute(30)->by('gif-search|' . $userId . '|' . $request->ip());
        });

        RateLimiter::for('newsletter-send', function (Request $request) {
            $userId = $request->user()?->id ?? $request->ip();
            return Limit::perMinute(2)->by('newsletter-send|' . $userId);
        });

        RateLimiter::for('newsletter-preview', function (Request $request) {
            $userId = $request->user()?->id ?? $request->ip();
            $perMinute = max(1, (int) config('newsletter.preview_rate_limit_per_minute', 20));

            return Limit::perMinute($perMinute)->by('newsletter-preview|' . $userId);
        });

        RateLimiter::for('me-export', function (Request $request) {
            $userId = $request->user()?->id ?? 'guest';

            return Limit::perMinute(1)->by('me-export|' . $userId . '|' . $request->ip());
        });
    }
}

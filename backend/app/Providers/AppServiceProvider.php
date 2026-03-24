<?php

namespace App\Providers;

use App\Console\Commands\ImportEventCandidates;
use App\Console\Commands\BotsPurgeCommand;
use App\Console\Commands\CrawlAstropixelsEventsCommand;
use App\Console\Commands\DiagnoseEventDescriptionCommand;
use App\Console\Commands\RunBotSourceCommand;
use App\Console\Commands\RunBotSchedulesCommand;
use App\Console\Commands\SendEventReminders;
use App\Console\Commands\SendEventNotificationReminders;
use App\Console\Commands\SendWeeklyNewsletterCommand;
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
use App\Services\Observing\Providers\OpenAqAirQualityProvider;
use App\Services\Observing\Providers\OpenMeteoWeatherProvider;
use App\Services\Observing\Providers\UsnoSunMoonProvider;
use App\Services\Translation\BotTranslationService;
use App\Services\Translation\Grammar\Contracts\GrammarCheckerInterface;
use App\Services\Translation\Grammar\LanguageToolGrammarChecker;
use App\Services\Translation\Grammar\OllamaGrammarChecker;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
        $this->app->bind(BotTranslationServiceInterface::class, BotTranslationService::class);
        $this->app->bind(GrammarCheckerInterface::class, function ($app) {
            $provider = strtolower(trim((string) config('translation.grammar.provider', 'languagetool')));

            return match ($provider) {
                'ollama' => $app->make(OllamaGrammarChecker::class),
                default => $app->make(LanguageToolGrammarChecker::class),
            };
        });

        $this->commands([
            ImportEventCandidates::class,
            BotsPurgeCommand::class,
            CrawlAstropixelsEventsCommand::class,
            RunBotSourceCommand::class,
            RunBotSchedulesCommand::class,
            SendEventReminders::class,
            SendEventNotificationReminders::class,
            SendWeeklyNewsletterCommand::class,
            GenerateEventDescriptionsCommand::class,
            DiagnoseEventDescriptionCommand::class,
        ]);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (function_exists('mb_internal_encoding')) {
            mb_internal_encoding('UTF-8');
        }

        $this->logTranslationEnvAliasUsage();

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

        RateLimiter::for('auth-password-reset-send', function (Request $request) {
            $email = mb_strtolower(trim((string) $request->input('email', '')));

            return Limit::perMinute(8)->by($request->ip() . '|password-reset-send|' . sha1($email));
        });

        RateLimiter::for('auth-password-reset-confirm', function (Request $request) {
            $email = mb_strtolower(trim((string) $request->input('email', '')));

            return Limit::perMinute(20)->by($request->ip() . '|password-reset-confirm|' . sha1($email));
        });

        RateLimiter::for('post-create', function (Request $request) {
            $userId = $request->user()?->id ?? 'guest';
            return Limit::perMinute(20)->by('post-create|' . $userId . '|' . $request->ip());
        });

        RateLimiter::for('gif-search', function (Request $request) {
            $userId = $request->user()?->id ?? 'guest';
            return Limit::perMinute(5)->by('gif-search|' . $userId . '|' . $request->ip());
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

        RateLimiter::for('admin-ai', function (Request $request) {
            $userId = (string) ($request->user()?->id ?? 'guest');
            $perMinute = max(1, (int) config('admin.ai_rate_limit_per_minute', 10));

            return Limit::perMinute($perMinute)->by('admin-ai|' . $userId . '|' . $request->ip());
        });

        RateLimiter::for('me-export', function (Request $request) {
            $userId = $request->user()?->id ?? 'guest';

            return Limit::perMinute(1)->by('me-export|' . $userId . '|' . $request->ip());
        });

        RateLimiter::for('me-export-jobs', function (Request $request) {
            $userId = (string) ($request->user()?->id ?? 'guest');

            return Limit::perMinute(5)->by('me-export-jobs|' . $userId);
        });

        RateLimiter::for('report-submissions', function (Request $request) {
            $userId = $request->user('sanctum')?->id ?? $request->user()?->id;
            if ($userId !== null) {
                return Limit::perMinute(10)->by('report-submit:user:' . $userId);
            }

            return Limit::perMinute(10)->by('report-submit:ip:' . $request->ip());
        });

        RateLimiter::for('sky-cheap-auth', function (Request $request) {
            $userId = $this->resolveSkyThrottleUserId($request);

            return Limit::perMinute(60)
                ->by('sky-cheap-auth|' . $userId);
        });

        RateLimiter::for('sky-expensive-auth', function (Request $request) {
            $userId = $this->resolveSkyThrottleUserId($request);

            return Limit::perMinute(20)
                ->by('sky-expensive-auth|' . $userId);
        });

        RateLimiter::for('sky-cheap-guest', function (Request $request) {
            return Limit::perMinute(30)
                ->by('sky-cheap-guest|' . $request->ip());
        });

        RateLimiter::for('sky-expensive-guest', function (Request $request) {
            return Limit::perMinute(10)
                ->by('sky-expensive-guest|' . $request->ip());
        });
    }

    private function resolveSkyThrottleUserId(Request $request): string
    {
        return (string) ($request->user('sanctum')?->id ?? $request->user()?->id ?? 'guest');
    }

    private function logTranslationEnvAliasUsage(): void
    {
        if (!config('app.debug')) {
            return;
        }

        $aliasUsage = [];

        $this->collectAliasUsage($aliasUsage, 'TRANSLATION_PROVIDER', ['BOT_TRANSLATION_PRIMARY']);
        $this->collectAliasUsage($aliasUsage, 'TRANSLATION_FALLBACK_PROVIDER', ['BOT_TRANSLATION_FALLBACK']);
        $this->collectAliasUsage($aliasUsage, 'TRANSLATION_TIMEOUT_SEC', ['TRANSLATION_TIMEOUT_SECONDS', 'BOT_TRANSLATION_LIBRETRANSLATE_TIMEOUT_SECONDS']);
        $this->collectAliasUsage($aliasUsage, 'TRANSLATION_MAX_RETRIES', ['BOT_TRANSLATION_LIBRETRANSLATE_RETRY_TIMES']);
        $this->collectAliasUsage($aliasUsage, 'LIBRETRANSLATE_BASE_URL', ['BOT_TRANSLATION_LIBRETRANSLATE_URL', 'TRANSLATION_BASE_URL']);
        $this->collectAliasUsage($aliasUsage, 'LIBRETRANSLATE_API_KEY', ['BOT_TRANSLATION_LIBRETRANSLATE_API_KEY']);
        $this->collectAliasUsage($aliasUsage, 'OLLAMA_MODEL', ['BOT_TRANSLATION_OLLAMA_MODEL', 'TRANSLATION_OLLAMA_MODEL']);
        $this->collectAliasUsage($aliasUsage, 'OLLAMA_NUM_PREDICT', ['BOT_TRANSLATION_OLLAMA_NUM_PREDICT', 'TRANSLATION_OLLAMA_NUM_PREDICT']);

        if ($aliasUsage === []) {
            return;
        }

        $cacheKey = 'translation:deprecated-env-aliases:logged';
        if (! Cache::add($cacheKey, true, now()->addMinutes(30))) {
            return;
        }

        Log::debug('Translation config using deprecated env aliases.', [
            'aliases_in_use' => $aliasUsage,
        ]);
    }

    /**
     * @param array<string,string> $aliasUsage
     * @param array<int,string> $aliases
     */
    private function collectAliasUsage(array &$aliasUsage, string $canonical, array $aliases): void
    {
        $canonicalValue = getenv($canonical);
        if (is_string($canonicalValue) && trim($canonicalValue) !== '') {
            return;
        }

        foreach ($aliases as $alias) {
            $aliasValue = getenv($alias);
            if (is_string($aliasValue) && trim($aliasValue) !== '') {
                $aliasUsage[$canonical] = $alias;
                return;
            }
        }
    }
}

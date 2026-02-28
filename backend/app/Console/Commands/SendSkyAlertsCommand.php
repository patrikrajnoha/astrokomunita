<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Services\NotificationService;
use App\Services\Sky\SkyIssPreviewService;
use App\Services\Sky\SkyWeatherService;
use App\Support\Sky\SkyContextResolver;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class SendSkyAlertsCommand extends Command
{
    protected $signature = 'notifications:send-sky-alerts';

    protected $description = 'Send ISS pass and good conditions in-app alerts for users that opted in.';

    public function __construct(
        private readonly SkyContextResolver $contextResolver,
        private readonly SkyIssPreviewService $issPreviewService,
        private readonly SkyWeatherService $weatherService,
        private readonly NotificationService $notificationService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $nowUtc = CarbonImmutable::now('UTC');
        $issSent = 0;
        $goodConditionsSent = 0;

        UserNotificationPreference::query()
            ->where(static function ($query): void {
                $query->where('iss_alerts', true)
                    ->orWhere('good_conditions_alerts', true);
            })
            ->with('user')
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($nowUtc, &$issSent, &$goodConditionsSent): void {
                foreach ($rows as $row) {
                    $user = $row->user;
                    if (!$user instanceof User || !$this->isEligibleUser($user)) {
                        continue;
                    }

                    $context = $this->resolveContextForUser($user);
                    if ($context === null) {
                        continue;
                    }

                    if ((bool) $row->iss_alerts) {
                        try {
                            if ($this->processIssAlert($user, $context, $nowUtc)) {
                                $issSent++;
                            }
                        } catch (\Throwable) {
                            // Keep command resilient per-user.
                        }
                    }

                    if ((bool) $row->good_conditions_alerts) {
                        try {
                            if ($this->processGoodConditionsAlert($user, $context, $nowUtc)) {
                                $goodConditionsSent++;
                            }
                        } catch (\Throwable) {
                            // Keep command resilient per-user.
                        }
                    }
                }
            });

        $this->info(sprintf(
            'Sky alerts complete. ISS sent: %d, good conditions sent: %d',
            $issSent,
            $goodConditionsSent
        ));

        return Command::SUCCESS;
    }

    /**
     * @param array{lat:float,lon:float,tz:string} $context
     */
    private function processIssAlert(User $user, array $context, CarbonImmutable $nowUtc): bool
    {
        $preview = $this->issPreviewService->fetch($context['lat'], $context['lon'], $context['tz']);
        if (($preview['available'] ?? false) !== true) {
            return false;
        }

        $nextPassAtRaw = $preview['next_pass_at'] ?? null;
        if (!is_string($nextPassAtRaw) || trim($nextPassAtRaw) === '') {
            return false;
        }

        try {
            $nextPassAt = CarbonImmutable::parse($nextPassAtRaw)->setTimezone($context['tz']);
        } catch (\Throwable) {
            return false;
        }

        $nowLocal = $nowUtc->setTimezone($context['tz']);
        if ($nextPassAt->lt($nowLocal) || $nextPassAt->gt($nowLocal->addMinutes(15))) {
            return false;
        }

        return $this->notificationService->createIssPassAlert($user->id, $preview) !== null;
    }

    /**
     * @param array{lat:float,lon:float,tz:string} $context
     */
    private function processGoodConditionsAlert(User $user, array $context, CarbonImmutable $nowUtc): bool
    {
        $nowLocal = $nowUtc->setTimezone($context['tz']);
        if ($nowLocal->hour < 18) {
            return false;
        }

        $weather = $this->weatherService->fetch($context['lat'], $context['lon'], $context['tz']);
        $score = is_numeric($weather['observing_score'] ?? null)
            ? (int) $weather['observing_score']
            : 0;

        if ($score <= 80) {
            return false;
        }

        return $this->notificationService->createGoodConditionsAlert(
            $user->id,
            $score,
            $nowLocal->format('Y-m-d')
        ) !== null;
    }

    private function isEligibleUser(User $user): bool
    {
        if ((bool) $user->is_bot) {
            return false;
        }

        return (bool) ($user->is_active ?? true);
    }

    /**
     * @return array{lat:float,lon:float,tz:string}|null
     */
    private function resolveContextForUser(User $user): ?array
    {
        $locationData = is_array($user->location_data ?? null) ? $user->location_data : [];
        $locationMeta = is_array($user->location_meta ?? null) ? $user->location_meta : [];

        $lat = $this->toLat($locationData['latitude'] ?? null) ?? $this->toLat($locationMeta['lat'] ?? null);
        $lon = $this->toLon($locationData['longitude'] ?? null) ?? $this->toLon($locationMeta['lon'] ?? null);
        if ($lat === null || $lon === null) {
            return null;
        }

        $tz = $this->normalizeTimezone($locationData['timezone'] ?? null)
            ?? $this->normalizeTimezone($locationMeta['tz'] ?? null)
            ?? $this->normalizeTimezone($user->timezone ?? null)
            ?? (string) config('observing.default_timezone', 'Europe/Bratislava');

        $request = Request::create('/api/sky/context', 'GET');
        $request->setUserResolver(static fn () => $user);

        $context = $this->contextResolver->resolve($request, [
            'lat' => $lat,
            'lon' => $lon,
            'tz' => $tz,
        ]);

        return [
            'lat' => $context['lat'],
            'lon' => $context['lon'],
            'tz' => $context['tz'],
        ];
    }

    private function toLat(mixed $value): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }

        $lat = (float) $value;
        return $lat >= -90.0 && $lat <= 90.0 ? $lat : null;
    }

    private function toLon(mixed $value): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }

        $lon = (float) $value;
        return $lon >= -180.0 && $lon <= 180.0 ? $lon : null;
    }

    private function normalizeTimezone(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        return in_array($trimmed, timezone_identifiers_list(), true) ? $trimmed : null;
    }
}

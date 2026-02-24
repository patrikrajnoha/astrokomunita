<?php

namespace App\Services\Translation;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Cache;

class TranslationOutageSimulationService
{
    public const SETTING_KEY = 'translation.simulate_outage_provider';
    private const CACHE_KEY = 'translation:simulate_outage_provider';

    public function getProvider(): string
    {
        $cached = Cache::get(self::CACHE_KEY);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $raw = AppSetting::getString(self::SETTING_KEY, 'none');
        $normalized = $this->normalizeProvider($raw);
        Cache::put(self::CACHE_KEY, $normalized, now()->addMinutes(10));

        return $normalized;
    }

    /**
     * @return array{old:string,new:string}
     */
    public function setProvider(?string $provider): array
    {
        $old = $this->getProvider();
        $new = $this->normalizeProvider($provider);

        if ($old !== $new) {
            AppSetting::put(self::SETTING_KEY, $new);
        }

        Cache::put(self::CACHE_KEY, $new, now()->addMinutes(10));

        return [
            'old' => $old,
            'new' => $new,
        ];
    }

    public function shouldSimulateFor(string $provider): bool
    {
        $outageProvider = $this->getProvider();
        $currentProvider = $this->normalizeProvider($provider);

        return $outageProvider !== 'none' && $outageProvider === $currentProvider;
    }

    private function normalizeProvider(?string $provider): string
    {
        $normalized = strtolower(trim((string) $provider));

        return in_array($normalized, ['none', 'ollama', 'libretranslate'], true)
            ? $normalized
            : 'none';
    }
}

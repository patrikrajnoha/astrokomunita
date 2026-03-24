<?php

namespace App\Services\Events;

use App\Models\AppSetting;
use Throwable;

class EventAiPolicyService
{
    public const OVERRIDE_SETTING_KEY = 'events.ai.policy.override.v1';

    private ?array $cachedBase = null;

    private ?array $cachedOverride = null;

    private ?array $cachedEffective = null;

    /**
     * @return array{
     *   effective:array<string,mixed>,
     *   override:array<string,mixed>,
     *   has_override:bool,
     *   setting_key:string
     * }
     */
    public function payload(): array
    {
        $override = $this->overridePolicy();

        return [
            'effective' => $this->effectivePolicy(),
            'override' => $override,
            'has_override' => $override !== [],
            'setting_key' => self::OVERRIDE_SETTING_KEY,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function effectivePolicy(): array
    {
        if ($this->cachedEffective !== null) {
            return $this->cachedEffective;
        }

        $base = $this->basePolicy();
        $override = $this->overridePolicy();
        $merged = $this->mergePolicy($base, $override);

        $this->cachedEffective = $this->normalizePolicy($merged, $base);

        return $this->cachedEffective;
    }

    public function value(string $path, mixed $default = null): mixed
    {
        return data_get($this->effectivePolicy(), $path, $default);
    }

    /**
     * @param array<string,mixed> $patch
     * @return array{
     *   effective:array<string,mixed>,
     *   override:array<string,mixed>,
     *   has_override:bool,
     *   setting_key:string
     * }
     */
    public function update(array $patch, bool $reset = false): array
    {
        if ($reset) {
            AppSetting::put(self::OVERRIDE_SETTING_KEY, null);
            $this->resetCache();

            return $this->payload();
        }

        $currentEffective = $this->effectivePolicy();
        $merged = $this->mergePolicy($currentEffective, $patch);
        $normalized = $this->normalizePolicy($merged, $this->basePolicy());

        $encoded = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        AppSetting::put(self::OVERRIDE_SETTING_KEY, is_string($encoded) ? $encoded : null);

        $this->resetCache();

        return $this->payload();
    }

    public function resetCache(): void
    {
        $this->cachedBase = null;
        $this->cachedOverride = null;
        $this->cachedEffective = null;
    }

    public static function isRegexPatternValid(string $pattern): bool
    {
        $normalized = self::normalizeRegexPattern($pattern);
        if ($normalized === null) {
            return false;
        }

        return @preg_match($normalized, '') !== false;
    }

    public static function normalizeRegexPattern(string $pattern): ?string
    {
        $value = trim($pattern);
        if ($value === '') {
            return null;
        }

        if (self::looksLikePcrePattern($value)) {
            return $value;
        }

        $delimiter = '~';
        $escaped = str_replace($delimiter, '\\' . $delimiter, $value);

        return $delimiter . $escaped . $delimiter . 'iu';
    }

    /**
     * @return array<string,mixed>
     */
    private function basePolicy(): array
    {
        if ($this->cachedBase !== null) {
            return $this->cachedBase;
        }

        $base = config('events_ai_policy', []);
        if (! is_array($base)) {
            $base = [];
        }

        $this->cachedBase = $this->normalizePolicy($base, $base);

        return $this->cachedBase;
    }

    /**
     * @return array<string,mixed>
     */
    private function overridePolicy(): array
    {
        if ($this->cachedOverride !== null) {
            return $this->cachedOverride;
        }

        try {
            $raw = AppSetting::getString(self::OVERRIDE_SETTING_KEY, null);
        } catch (Throwable) {
            $this->cachedOverride = [];

            return $this->cachedOverride;
        }

        if (! is_string($raw) || trim($raw) === '') {
            $this->cachedOverride = [];

            return $this->cachedOverride;
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            $this->cachedOverride = [];

            return $this->cachedOverride;
        }

        $this->cachedOverride = $this->normalizePolicy($decoded, $this->basePolicy());

        return $this->cachedOverride;
    }

    /**
     * @param array<string,mixed> $base
     * @param array<string,mixed> $patch
     * @return array<string,mixed>
     */
    private function mergePolicy(array $base, array $patch): array
    {
        foreach ($patch as $key => $value) {
            $baseValue = $base[$key] ?? null;

            if (
                is_array($baseValue)
                && is_array($value)
                && $this->isAssociativeArray($baseValue)
                && $this->isAssociativeArray($value)
            ) {
                $base[$key] = $this->mergePolicy($baseValue, $value);
                continue;
            }

            // Indexed arrays (rules/terms/forbidden lists) should replace fully.
            $base[$key] = $value;
        }

        return $base;
    }

    /**
     * @param array<string,mixed> $candidate
     * @param array<string,mixed> $fallback
     * @return array<string,mixed>
     */
    private function normalizePolicy(array $candidate, array $fallback): array
    {
        $normalized = [];

        $legacyFallback = $this->stringList(data_get($fallback, 'prompts.legacy.rules', []));
        $humanizedFallback = $this->stringList(data_get($fallback, 'prompts.humanized.rules', []));

        $normalized['prompts'] = [
            'legacy' => [
                'rules' => $this->stringList(
                    data_get($candidate, 'prompts.legacy.rules', $legacyFallback),
                    $legacyFallback
                ),
            ],
            'humanized' => [
                'rules' => $this->stringList(
                    data_get($candidate, 'prompts.humanized.rules', $humanizedFallback),
                    $humanizedFallback
                ),
            ],
        ];

        $numericFallback = (bool) data_get($fallback, 'safety.numeric_token_guard_enabled', true);
        $celestialFallback = (bool) data_get($fallback, 'safety.celestial_term_guard_enabled', true);
        $artifactFallback = (bool) data_get($fallback, 'safety.artifact_guard_enabled', true);

        $celestialTermsFallback = $this->stringList(data_get($fallback, 'safety.celestial_terms', []));
        $forbiddenSubstringsFallback = $this->stringList(data_get($fallback, 'safety.forbidden_substrings', []));
        $forbiddenRegexFallback = $this->stringList(data_get($fallback, 'safety.forbidden_regex', []));

        $forbiddenRegex = $this->stringList(
            data_get($candidate, 'safety.forbidden_regex', $forbiddenRegexFallback),
            $forbiddenRegexFallback
        );
        $forbiddenRegex = array_values(array_filter(
            $forbiddenRegex,
            static fn (string $pattern): bool => self::isRegexPatternValid($pattern)
        ));
        if ($forbiddenRegex === []) {
            $forbiddenRegex = $forbiddenRegexFallback;
        }

        $normalized['safety'] = [
            'numeric_token_guard_enabled' => (bool) data_get(
                $candidate,
                'safety.numeric_token_guard_enabled',
                $numericFallback
            ),
            'celestial_term_guard_enabled' => (bool) data_get(
                $candidate,
                'safety.celestial_term_guard_enabled',
                $celestialFallback
            ),
            'artifact_guard_enabled' => (bool) data_get(
                $candidate,
                'safety.artifact_guard_enabled',
                $artifactFallback
            ),
            'celestial_terms' => $this->stringList(
                data_get($candidate, 'safety.celestial_terms', $celestialTermsFallback),
                $celestialTermsFallback
            ),
            'forbidden_substrings' => $this->stringList(
                data_get($candidate, 'safety.forbidden_substrings', $forbiddenSubstringsFallback),
                $forbiddenSubstringsFallback
            ),
            'forbidden_regex' => $forbiddenRegex,
        ];

        return $normalized;
    }

    /**
     * @param mixed $value
     * @param array<int,string> $fallback
     * @return array<int,string>
     */
    private function stringList(mixed $value, array $fallback = []): array
    {
        if (! is_array($value)) {
            return $fallback;
        }

        $normalized = array_values(array_filter(array_map(
            static fn (mixed $item): string => is_string($item) ? trim($item) : '',
            $value
        ), static fn (string $item): bool => $item !== ''));

        return $normalized !== [] ? $normalized : $fallback;
    }

    /**
     * @param array<mixed> $value
     */
    private function isAssociativeArray(array $value): bool
    {
        if ($value === []) {
            return false;
        }

        return array_keys($value) !== range(0, count($value) - 1);
    }

    private static function looksLikePcrePattern(string $value): bool
    {
        if (strlen($value) < 3) {
            return false;
        }

        $delimiter = $value[0];
        if (ctype_alnum($delimiter) || $delimiter === '\\') {
            return false;
        }

        $end = strrpos($value, $delimiter);
        if ($end === false || $end === 0) {
            return false;
        }

        $modifiers = substr($value, $end + 1);
        if ($modifiers === '') {
            return true;
        }

        return preg_match('/^[imsxuADSUXJ]*$/', $modifiers) === 1;
    }
}

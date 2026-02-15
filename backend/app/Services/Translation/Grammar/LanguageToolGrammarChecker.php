<?php

namespace App\Services\Translation\Grammar;

use App\Services\Translation\Grammar\Contracts\GrammarCheckerInterface;
use App\Support\Http\SslVerificationPolicy;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

class LanguageToolGrammarChecker implements GrammarCheckerInterface
{
    public function correct(string $text, string $language): GrammarCheckResult
    {
        $startedAt = microtime(true);
        $value = trim($text);

        if ($value === '') {
            return new GrammarCheckResult(
                correctedText: $text,
                provider: 'none',
                appliedFixes: 0,
                durationMs: 0
            );
        }

        $config = (array) config('translation.grammar.languagetool', []);
        $baseUrl = (string) ($config['base_url'] ?? 'http://127.0.0.1:8081');
        $path = (string) ($config['check_path'] ?? '/v2/check');
        $languageCode = trim($language) !== '' ? $language : (string) ($config['language'] ?? 'sk-SK');
        $verifyOption = app(SslVerificationPolicy::class)->resolveVerifyOption(
            allowInsecure: ! (bool) ($config['verify_ssl'] ?? true)
        );

        try {
            $response = Http::baseUrl($baseUrl)
                ->timeout((int) ($config['timeout'] ?? 6))
                ->connectTimeout((int) ($config['connect_timeout'] ?? 2))
                ->retry(
                    (int) ($config['retry'] ?? 1),
                    (int) ($config['retry_sleep_ms'] ?? 200),
                    null,
                    false
                )
                ->withOptions([
                    'verify' => $verifyOption,
                ])
                ->withAttributes(['ssl_verify' => $verifyOption])
                ->acceptJson()
                ->withHeaders($this->resolveHeaders($config))
                ->asForm()
                ->post($path, $this->buildPayload($config, $value, $languageCode));
        } catch (ConnectionException) {
            throw new GrammarCheckException('LanguageTool connection failed.', 'languagetool_connection_error');
        } catch (Throwable) {
            throw new GrammarCheckException('LanguageTool request failed.', 'languagetool_service_error');
        }

        if (! $response->successful()) {
            throw new GrammarCheckException(
                'LanguageTool failed with HTTP ' . $response->status() . '.',
                'languagetool_http_' . $response->status(),
                $response->status()
            );
        }

        $matches = $response->json('matches');
        if (! is_array($matches)) {
            throw new GrammarCheckException('LanguageTool response is invalid.', 'languagetool_invalid_response');
        }

        $appliedFixes = 0;
        $corrected = $this->applyMatches(
            text: $value,
            matches: $matches,
            maxFixes: max(0, (int) ($config['max_fixes'] ?? 30)),
            appliedFixes: $appliedFixes
        );

        return new GrammarCheckResult(
            correctedText: $corrected,
            provider: 'languagetool',
            appliedFixes: $appliedFixes,
            durationMs: (int) round((microtime(true) - $startedAt) * 1000),
            meta: [
                'matches_total' => count($matches),
                'language' => $languageCode,
            ]
        );
    }

    /**
     * @param array<string,mixed> $config
     * @return array<string,string>
     */
    private function resolveHeaders(array $config): array
    {
        $headers = [];
        $token = trim((string) ($config['internal_token'] ?? ''));

        if ($token !== '') {
            $headers['X-Internal-Token'] = $token;
        }

        return $headers;
    }

    /**
     * @param array<string,mixed> $config
     * @return array<string,mixed>
     */
    private function buildPayload(array $config, string $text, string $language): array
    {
        $payload = [
            'text' => $text,
            'language' => $language,
        ];

        $enabledRules = $this->normalizeCsvList((string) ($config['enabled_rules'] ?? ''));
        if ($enabledRules !== '') {
            $payload['enabledRules'] = $enabledRules;
        }

        $disabledRules = $this->normalizeCsvList((string) ($config['disabled_rules'] ?? ''));
        if ($disabledRules !== '') {
            $payload['disabledRules'] = $disabledRules;
        }

        if (array_key_exists('enabled_only', $config)) {
            $payload['enabledOnly'] = (bool) $config['enabled_only'] ? 'true' : 'false';
        }

        return $payload;
    }

    private function normalizeCsvList(string $value): string
    {
        if (trim($value) === '') {
            return '';
        }

        $parts = array_map(
            static fn (string $part): string => trim($part),
            explode(',', $value)
        );

        $parts = array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));
        return implode(',', $parts);
    }

    /**
     * @param array<int,mixed> $matches
     */
    private function applyMatches(string $text, array $matches, int $maxFixes, int &$appliedFixes): string
    {
        $edits = [];
        foreach ($matches as $match) {
            if (! is_array($match)) {
                continue;
            }

            $offset = $match['offset'] ?? null;
            $length = $match['length'] ?? null;

            if (! is_int($offset) || ! is_int($length) || $offset < 0 || $length < 0) {
                continue;
            }

            $replacement = $this->firstReplacement($match['replacements'] ?? null);
            if ($replacement === null) {
                continue;
            }

            $edits[] = [
                'offset' => $offset,
                'length' => $length,
                'replacement' => $replacement,
            ];
        }

        if ($edits === []) {
            $appliedFixes = 0;
            return $text;
        }

        usort($edits, static fn (array $left, array $right): int => $right['offset'] <=> $left['offset']);
        if ($maxFixes > 0 && count($edits) > $maxFixes) {
            $edits = array_slice($edits, 0, $maxFixes);
        }

        $chars = $this->splitChars($text);
        $charCount = count($chars);
        $applied = 0;

        foreach ($edits as $edit) {
            $offset = max(0, min($edit['offset'], $charCount));
            $length = max(0, min($edit['length'], $charCount - $offset));
            $replacementChars = $this->splitChars($edit['replacement']);

            $current = array_slice($chars, $offset, $length);
            if ($current === $replacementChars) {
                continue;
            }

            array_splice($chars, $offset, $length, $replacementChars);
            $charCount = count($chars);
            $applied++;
        }

        $appliedFixes = $applied;
        return implode('', $chars);
    }

    /**
     * @param mixed $replacements
     */
    private function firstReplacement(mixed $replacements): ?string
    {
        if (! is_array($replacements)) {
            return null;
        }

        foreach ($replacements as $replacement) {
            if (! is_array($replacement)) {
                continue;
            }

            $value = $replacement['value'] ?? null;
            if (! is_string($value)) {
                continue;
            }

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @return array<int,string>
     */
    private function splitChars(string $value): array
    {
        if ($value === '') {
            return [];
        }

        $chars = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
        return is_array($chars) ? $chars : [$value];
    }
}

<?php

namespace App\Services\AI;

use JsonException;

class JsonGuard
{
    /**
     * @return array{valid:bool,data:array<string,mixed>,errors:array<int,string>}
     */
    public function parseJsonObject(string $responseText): array
    {
        $raw = trim($responseText);
        if ($raw === '') {
            return $this->failedMixed(['empty_response']);
        }

        $jsonObject = $this->extractFirstJsonObject($raw);
        if ($jsonObject === null) {
            return $this->failedMixed(['json_object_not_found']);
        }

        try {
            $decoded = json_decode($jsonObject, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->failedMixed(['json_decode_failed']);
        }

        if (! is_array($decoded) || array_is_list($decoded)) {
            return $this->failedMixed(['json_root_not_object']);
        }

        return [
            'valid' => true,
            'data' => $decoded,
            'errors' => [],
        ];
    }

    /**
     * @param array<string,int> $stringLengthRules
     * @return array{valid:bool,data:array<string,string>,errors:array<int,string>}
     */
    public function parseAndValidate(
        string $responseText,
        array $stringLengthRules,
        bool $allowExtraKeys = true
    ): array {
        $parsed = $this->parseJsonObject($responseText);
        if (! $parsed['valid']) {
            return $this->failed((array) $parsed['errors']);
        }
        $decoded = (array) $parsed['data'];

        $errors = [];
        $normalized = [];

        foreach ($stringLengthRules as $key => $maxLength) {
            if (! array_key_exists($key, $decoded)) {
                $errors[] = 'missing_key:' . $key;
                continue;
            }

            $value = $decoded[$key];
            if (! is_string($value)) {
                $errors[] = 'invalid_type:' . $key;
                continue;
            }

            $normalizedValue = $this->normalizeText($value);
            if ($this->length($normalizedValue) > max(1, (int) $maxLength)) {
                $errors[] = 'max_length_exceeded:' . $key;
                continue;
            }

            $normalized[$key] = $normalizedValue;
        }

        if (! $allowExtraKeys) {
            foreach (array_keys($decoded) as $key) {
                if (! array_key_exists($key, $stringLengthRules)) {
                    $errors[] = 'unexpected_key:' . $key;
                }
            }
        }

        if ($errors !== []) {
            return $this->failed($errors);
        }

        return [
            'valid' => true,
            'data' => $normalized,
            'errors' => [],
        ];
    }

    private function normalizeText(string $value): string
    {
        $plain = trim(strip_tags($value));
        if ($plain === '') {
            return '';
        }

        $plain = preg_replace('/\s+/u', ' ', $plain) ?? $plain;
        return trim($plain);
    }

    private function length(string $value): int
    {
        return function_exists('mb_strlen')
            ? mb_strlen($value, 'UTF-8')
            : strlen($value);
    }

    private function extractFirstJsonObject(string $text): ?string
    {
        $value = trim($text);
        if ($value === '') {
            return null;
        }

        $length = strlen($value);
        for ($start = 0; $start < $length; $start++) {
            if ($value[$start] !== '{') {
                continue;
            }

            $depth = 0;
            $inString = false;
            $escaped = false;

            for ($cursor = $start; $cursor < $length; $cursor++) {
                $char = $value[$cursor];

                if ($inString) {
                    if ($escaped) {
                        $escaped = false;
                        continue;
                    }

                    if ($char === '\\') {
                        $escaped = true;
                        continue;
                    }

                    if ($char === '"') {
                        $inString = false;
                    }

                    continue;
                }

                if ($char === '"') {
                    $inString = true;
                    continue;
                }

                if ($char === '{') {
                    $depth++;
                    continue;
                }

                if ($char !== '}') {
                    continue;
                }

                $depth--;
                if ($depth === 0) {
                    return substr($value, $start, $cursor - $start + 1);
                }
            }
        }

        return null;
    }

    /**
     * @param array<int,string> $errors
     * @return array{valid:bool,data:array<string,string>,errors:array<int,string>}
     */
    private function failed(array $errors): array
    {
        return [
            'valid' => false,
            'data' => [],
            'errors' => $errors,
        ];
    }

    /**
     * @param array<int,string> $errors
     * @return array{valid:bool,data:array<string,mixed>,errors:array<int,string>}
     */
    private function failedMixed(array $errors): array
    {
        return [
            'valid' => false,
            'data' => [],
            'errors' => $errors,
        ];
    }
}

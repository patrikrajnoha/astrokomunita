<?php

namespace App\Services\Bots\Exceptions;

use RuntimeException;

class BotFetchException extends RuntimeException
{
    public static function forHttpFailure(string $url, int $status, ?string $body = null): self
    {
        return new self(sprintf(
            'RSS fetch failed (url=%s, status=%d, snippet="%s")',
            $url,
            $status,
            self::snippet($body)
        ));
    }

    public static function forNetworkFailure(string $url, string $message): self
    {
        return new self(sprintf(
            'RSS fetch failed (url=%s, status=network_error, snippet="%s")',
            $url,
            self::snippet($message)
        ));
    }

    public static function forInvalidContentType(string $url, ?string $contentType, ?string $body = null): self
    {
        $type = trim((string) $contentType);
        if ($type === '') {
            $type = 'unknown';
        }

        return new self(sprintf(
            'RSS fetch failed (url=%s, status=invalid_content_type:%s, snippet="%s")',
            $url,
            $type,
            self::snippet($body)
        ));
    }

    public static function forInvalidXml(string $url, ?string $body = null): self
    {
        return new self(sprintf(
            'RSS fetch failed (url=%s, status=invalid_xml, snippet="%s")',
            $url,
            self::snippet($body)
        ));
    }

    private static function snippet(?string $value): string
    {
        $text = preg_replace('/\s+/u', ' ', trim((string) $value)) ?? '';
        if ($text === '') {
            return 'n/a';
        }

        if (function_exists('mb_substr')) {
            return mb_substr($text, 0, 500);
        }

        return substr($text, 0, 500);
    }
}

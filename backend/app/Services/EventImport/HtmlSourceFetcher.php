<?php

namespace App\Services\EventImport;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class HtmlSourceFetcher
{
    private const REQUEST_HEADERS = [
        'Accept-Language' => 'sk-SK,sk;q=0.9,en;q=0.8',
        'User-Agent' => 'AstrokomunitaCrawler/1.0 (+https://astropixels.com; research-use)',
    ];
    private const HUMANS_CHALLENGE_COOKIE_PATTERN = '/document\.cookie\s*=\s*"([A-Za-z0-9_]+=[^";]+)"/i';

    public function fetch(string $url): string
    {
        // Podpora lokálneho súboru: file://C:/path/to/file.html (Windows)
        // Podpora aj: file:///C:/path/to/file.html
        if (str_starts_with($url, 'file://')) {
            $path = substr($url, 7);     // odstráň "file://"
            $path = ltrim($path, '/');   // ak je file:///C:/..., odstráň úvodné "/"
            $path = str_replace('/', DIRECTORY_SEPARATOR, $path);

            if (!is_file($path)) {
                throw new RuntimeException("Local file not found: {$path}");
            }

            $content = @file_get_contents($path);
            if ($content === false) {
                throw new RuntimeException("Cannot read local file: {$path}");
            }

            return $content;
        }

        $response = $this->fetchRemoteHtml($url);

        return $response->throw()->body();
    }

    private function fetchRemoteHtml(string $url): Response
    {
        $response = $this->requestHtml($url);

        if ($response->status() !== 409) {
            return $response;
        }

        $challengeCookie = $this->extractHumansChallengeCookie((string) $response->body());
        if ($challengeCookie === null) {
            return $response;
        }

        return $this->requestHtml($url, [
            'Cookie' => $challengeCookie,
        ]);
    }

    /**
     * @param array<string,string> $extraHeaders
     */
    private function requestHtml(string $url, array $extraHeaders = []): Response
    {
        return Http::secure()
            ->accept('text/html')
            ->withHeaders(array_merge(self::REQUEST_HEADERS, $extraHeaders))
            ->get($url);
    }

    private function extractHumansChallengeCookie(string $body): ?string
    {
        if ($body === '' || ! str_contains($body, 'document.cookie')) {
            return null;
        }

        if (preg_match(self::HUMANS_CHALLENGE_COOKIE_PATTERN, $body, $matches) !== 1) {
            return null;
        }

        $cookie = trim((string) ($matches[1] ?? ''));

        return $cookie !== '' ? $cookie : null;
    }
}

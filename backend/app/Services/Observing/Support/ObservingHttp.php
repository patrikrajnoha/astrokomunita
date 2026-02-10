<?php

namespace App\Services\Observing\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;

class ObservingHttp
{
    public function __construct(
        private readonly HttpFactory $http
    ) {
    }

    public function getJson(
        string $provider,
        string $url,
        array $query = [],
        array $headers = [],
        bool $authenticated = false
    ): array {
        $request = $this->buildRequest($headers);

        $safeUrl = $this->buildSafeUrl($url, $query);

        try {
            $response = $request->get($url, $query);

            if (!$response->successful()) {
                $this->logFailure(
                    $provider,
                    $safeUrl,
                    $response->status(),
                    $this->bodySnippet($response)
                );

                throw new ObservingProviderException(
                    "{$provider} request failed with status {$response->status()}",
                    $provider,
                    $safeUrl,
                    $response->status(),
                    $this->bodySnippet($response)
                );
            }

            $decoded = $response->json();
            if (!is_array($decoded)) {
                throw new ObservingProviderException(
                    "{$provider} returned non-JSON payload.",
                    $provider,
                    $safeUrl,
                    $response->status(),
                    $this->bodySnippet($response)
                );
            }

            return $decoded;
        } catch (ObservingProviderException $exception) {
            throw $exception;
        } catch (RequestException $exception) {
            $response = $exception->response;
            $status = $response?->status();
            $snippet = $response ? $this->bodySnippet($response) : null;
            $this->logFailure($provider, $safeUrl, $status, $snippet, $exception);

            throw new ObservingProviderException(
                "{$provider} request exception: {$exception->getMessage()}",
                $provider,
                $safeUrl,
                $status,
                $snippet,
                $exception
            );
        } catch (ConnectionException $exception) {
            $this->logFailure($provider, $safeUrl, null, null, $exception);

            throw new ObservingProviderException(
                "{$provider} connection exception: {$exception->getMessage()}",
                $provider,
                $safeUrl,
                null,
                null,
                $exception
            );
        } catch (\Throwable $exception) {
            $this->logFailure($provider, $safeUrl, null, null, $exception);

            throw new ObservingProviderException(
                "{$provider} unexpected exception: {$exception->getMessage()}",
                $provider,
                $safeUrl,
                null,
                null,
                $exception
            );
        }
    }

    private function buildRequest(array $headers = []): PendingRequest
    {
        $request = $this->http
            ->timeout((int) config('observing.http.timeout_seconds', 8))
            ->retry(
                (int) config('observing.http.retry_times', 2),
                (int) config('observing.http.retry_sleep_ms', 200)
            )
            ->acceptJson();

        $verifyPath = (string) config('observing.http.local_ca_bundle_path', '');
        if (app()->environment('local') && $verifyPath !== '' && file_exists($verifyPath)) {
            $request = $request->withOptions(['verify' => $verifyPath]);
        }

        if ($headers !== []) {
            $request = $request->withHeaders($headers);
        }

        return $request;
    }

    private function buildSafeUrl(string $url, array $query): string
    {
        if ($query === []) {
            return $url;
        }

        $queryString = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        return "{$url}?{$queryString}";
    }

    private function bodySnippet(Response $response): ?string
    {
        $body = trim($response->body());
        if ($body === '') {
            return null;
        }

        return mb_substr($body, 0, 300);
    }

    private function logFailure(
        string $provider,
        string $safeUrl,
        ?int $status = null,
        ?string $snippet = null,
        ?\Throwable $exception = null
    ): void {
        Log::warning('Observing provider HTTP failure.', [
            'provider' => $provider,
            'url' => $safeUrl,
            'status' => $status,
            'body_snippet' => $snippet,
            'exception_class' => $exception ? $exception::class : null,
            'exception_message' => $exception?->getMessage(),
        ]);
    }
}


<?php

namespace App\Services\DistributorPlatforms\Concerns;

use Illuminate\Http\Client\Response;

/**
 * Shared helpers for the platform adapters: classifying a response as a block /
 * rate-limit / bot-challenge, honouring Retry-After, reading throttle config,
 * and sleeping with jitter.
 */
trait InspectsHttpResponses
{
    /**
     * Classify a response. Returns null when it's a usable success, otherwise a
     * reason code: rate_limited | blocked | challenge | http_error.
     */
    protected function classifyResponse(Response $response): ?string
    {
        $status = $response->status();

        return match (true) {
            $status === 429 => 'rate_limited',
            $status === 403 => 'blocked',
            $status === 503 => $this->looksLikeChallenge($response) ? 'challenge' : 'blocked',
            $status >= 400 => 'http_error',
            $this->looksLikeChallenge($response) => 'challenge',
            default => null,
        };
    }

    /**
     * Reasons worth retrying after a back-off (the remote may let us through
     * shortly). A plain http_error is not retried.
     */
    protected function isRetryable(?string $reason): bool
    {
        return in_array($reason, ['rate_limited', 'blocked', 'challenge'], true);
    }

    /**
     * Detect an interstitial bot-challenge (typically Cloudflare) returned in
     * place of the real payload — often a 200 or 503 whose body is HTML.
     */
    protected function looksLikeChallenge(Response $response): bool
    {
        if (strtolower((string) $response->header('cf-mitigated')) === 'challenge') {
            return true;
        }

        $contentType = strtolower((string) $response->header('Content-Type'));
        $body = ltrim($response->body());
        $lowerBody = strtolower($body);

        $isHtml = str_contains($contentType, 'text/html')
            || str_starts_with($lowerBody, '<!doctype html')
            || str_starts_with($lowerBody, '<html');

        if (! $isHtml) {
            return false;
        }

        foreach (['just a moment', 'cf-browser-verification', 'attention required', 'cf_chl', 'enable javascript and cookies'] as $marker) {
            if (str_contains($lowerBody, $marker)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Seconds to wait before the next retry: honour a numeric Retry-After
     * header (capped), otherwise linear back-off.
     */
    protected function retryDelaySeconds(Response $response, int $attempt, int $baseSeconds): int
    {
        $retryAfter = $response->header('Retry-After');

        if (is_numeric($retryAfter)) {
            return min(120, max(1, (int) $retryAfter));
        }

        return $attempt * $baseSeconds;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function configInt(array $config, string $key, int $default): int
    {
        return isset($config[$key]) && is_numeric($config[$key]) ? (int) $config[$key] : $default;
    }

    /**
     * Sleep for delayMs plus up to jitterMs of random jitter. Jitter makes the
     * request cadence look less robotic, which helps with bot defences on slow,
     * long-running crawls.
     */
    protected function throttle(int $delayMs, int $jitterMs): void
    {
        $total = $delayMs + ($jitterMs > 0 ? random_int(0, $jitterMs) : 0);

        if ($total > 0) {
            usleep($total * 1000);
        }
    }
}

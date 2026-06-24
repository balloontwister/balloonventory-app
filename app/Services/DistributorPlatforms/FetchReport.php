<?php

namespace App\Services\DistributorPlatforms;

/**
 * Diagnostics for a single distributor fetch run, so the sync command can tell
 * "the store legitimately has N products" apart from "we got blocked / rate
 * limited / challenged and bailed with a truncated list".
 */
class FetchReport
{
    public int $pagesFetched = 0;

    public int $retriesUsed = 0;

    /** True when the fetch stopped on an error before reaching the natural end of the catalog. */
    public bool $stoppedEarly = false;

    /** True when a richer source (e.g. Shopify JSON) was unavailable and we dropped to the sitemap. */
    public bool $usedFallback = false;

    public ?int $lastFailureStatus = null;

    /** rate_limited | blocked | challenge | http_error | unparseable */
    public ?string $lastFailureReason = null;

    public function recordFailure(?int $status, ?string $reason): void
    {
        $this->stoppedEarly = true;
        $this->lastFailureStatus = $status;
        $this->lastFailureReason = $reason;
    }

    /**
     * Whether the stop looks like the remote side actively pushing us away
     * (vs. a plain HTTP error or a malformed page).
     */
    public function looksBlocked(): bool
    {
        return in_array($this->lastFailureReason, ['rate_limited', 'blocked', 'challenge'], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'pages_fetched' => $this->pagesFetched,
            'retries_used' => $this->retriesUsed,
            'stopped_early' => $this->stoppedEarly,
            'used_fallback' => $this->usedFallback,
            'last_failure_status' => $this->lastFailureStatus,
            'last_failure_reason' => $this->lastFailureReason,
        ];
    }
}

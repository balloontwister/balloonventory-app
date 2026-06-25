<?php

namespace App\Jobs;

use App\Models\Distributor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Runs a distributor's staging ingest off the request path. Shopify distributors
 * pull their bulk products.json in one pass; BigCommerce stores are crawled a
 * page at a time, so this job stages up to {@see $pageLimit} pages per dispatch
 * and the admin re-dispatches ("Crawl more") to continue — matching how
 * catalog:crawl-distributor resumes via fetched_at.
 */
class RunDistributorSyncJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 900;

    public function __construct(
        public string $distributorId,
        public int $pageLimit = 100,
    ) {}

    public function handle(): void
    {
        $distributor = Distributor::find($this->distributorId);

        if ($distributor === null) {
            return;
        }

        [$command, $arguments] = $distributor->platform_type === 'bigcommerce'
            ? ['catalog:crawl-distributor', ['slug' => $distributor->slug, '--execute' => true, '--limit' => $this->pageLimit]]
            : ['catalog:ingest-distributor', ['slug' => $distributor->slug, '--execute' => true]];

        try {
            Artisan::call($command, $arguments);
        } catch (\Throwable $e) {
            Log::error('Distributor sync job failed', [
                'distributor' => $distributor->slug,
                'command' => $command,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}

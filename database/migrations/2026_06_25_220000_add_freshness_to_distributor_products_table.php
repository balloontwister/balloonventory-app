<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Freshness tracking for incremental crawls:
 *  - last_seen_at: when the product last appeared in the distributor's sitemap.
 *  - removed_at:   set when a previously-staged product drops out of a complete
 *                  sitemap (discontinued); cleared if it reappears.
 *
 * Together with the sitemap's <lastmod>, these let a refresh fetch only new and
 * changed product pages and retire ones that are gone, instead of re-crawling the
 * whole catalog. Lives on the relocatable `distributors` connection like the rest
 * of staging.
 */
return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('distributors.connection');
    }

    public function up(): void
    {
        Schema::connection($this->getConnection())->table('distributor_products', function (Blueprint $table) {
            $table->timestamp('last_seen_at')->nullable()->after('fetched_at');
            $table->timestamp('removed_at')->nullable()->after('last_seen_at');
            $table->index('removed_at');
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->table('distributor_products', function (Blueprint $table) {
            $table->dropIndex(['removed_at']);
            $table->dropColumn(['last_seen_at', 'removed_at']);
        });
    }
};

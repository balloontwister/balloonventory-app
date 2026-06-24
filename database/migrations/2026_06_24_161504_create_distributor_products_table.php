<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staging table: the raw product listings fetched/crawled from each distributor,
 * decoupled from the catalog so a slow crawl can write incrementally and resume,
 * and the cluster engine can group across distributors in a separate pass.
 *
 * Lives on the relocatable `distributors` connection (config/distributors.php),
 * so it carries NO database-level foreign keys — `distributor_id` is an indexed
 * reference, integrity enforced in app code.
 */
return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('distributors.connection');
    }

    public function up(): void
    {
        Schema::connection($this->getConnection())->create('distributor_products', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('distributor_id', 36);

            // Stable per-listing key from the distributor (variant id / sku / url
            // hash) — the writer guarantees stability so re-crawls upsert.
            $table->string('external_id');

            $table->string('raw_sku')->nullable();
            $table->string('normalized_sku')->nullable();
            $table->string('upc', 32)->nullable();
            $table->string('title')->nullable();
            $table->string('url', 2048);
            $table->decimal('price', 10, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->unsignedInteger('stock')->nullable();
            $table->boolean('in_stock')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();

            $table->unique(['distributor_id', 'external_id']);
            $table->index('distributor_id');
            $table->index('normalized_sku');
            $table->index('upc');
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('distributor_products');
    }
};

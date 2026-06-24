<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A cross-distributor product cluster the system proposes adding to the catalog.
 * Clustering is UPC-gated: a cluster only exists when ≥1 distributor exposes a
 * barcode, which becomes the cluster's canonical identity.
 *
 * Review state is baked in from day one so the (later-built) admin review/browse
 * UI is pure controller+Vue over this table — no schema rework. High-confidence
 * clusters are written `auto_approved` with a resulting_sku_id; everything else
 * is `pending` for manual approval/edit.
 *
 * Lives on the relocatable `distributors` connection — no DB-level foreign keys.
 */
return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('distributors.connection');
    }

    public function up(): void
    {
        Schema::connection($this->getConnection())->create('distributor_catalog_proposals', function (Blueprint $table) {
            $table->char('id', 36)->primary();

            // Canonical GTIN-14 — the cluster's identity and natural unique key.
            $table->string('upc', 32);
            $table->string('normalized_sku')->nullable();

            $table->string('status', 20)->default('pending'); // pending|auto_approved|approved|rejected
            $table->string('confidence', 20)->nullable();     // high|low (or numeric later)

            // Proposed catalog attributes (resolved reference-row ids when known).
            $table->char('proposed_brand_id', 36)->nullable();
            $table->char('proposed_balloon_size_id', 36)->nullable();
            $table->char('proposed_color_id', 36)->nullable();
            $table->unsignedInteger('proposed_count')->nullable();
            $table->string('proposed_name')->nullable();
            $table->string('proposed_warehouse_sku')->nullable();

            // The contributing distributor listings (distributor_id, sku, url,
            // stock, price, title) — the evidence shown in the review UI.
            $table->json('evidence')->nullable();

            // Set once promoted into the catalog.
            $table->char('resulting_sku_id', 36)->nullable();

            $table->char('reviewed_by', 36)->nullable();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            $table->unique('upc');
            $table->index('status');
            $table->index('normalized_sku');
            $table->index('resulting_sku_id');
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('distributor_catalog_proposals');
    }
};

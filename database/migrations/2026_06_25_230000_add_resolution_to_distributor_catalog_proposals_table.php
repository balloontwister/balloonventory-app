<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Denormalised matcher resolution, stamped on each proposal at cluster time.
 *
 * The review queue needs to sort/group/count by brand and by how resolvable a
 * proposal is — but those are computed live by the matcher and aren't columns, so
 * the queue can't order or aggregate by them and re-runs the matcher over every
 * pending proposal to build the "missing reference data" panel. These columns make
 * the resolution a stored fact:
 *  - resolved_brand_id / resolved_brand_name: the matcher's brand (for grouping).
 *  - resolution_state: full | partial | no_brand (for "one-click first" ordering).
 *  - resolution: the per-attribute detail (resolved ids/names or the unresolved
 *    distributor value) the gaps panel aggregates without touching the matcher.
 *
 * Refreshed on every (re)cluster. Lives on the relocatable `distributors`
 * connection like the rest of the proposal data.
 */
return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('distributors.connection');
    }

    public function up(): void
    {
        Schema::connection($this->getConnection())->table('distributor_catalog_proposals', function (Blueprint $table) {
            $table->char('resolved_brand_id', 36)->nullable()->after('confidence');
            $table->string('resolved_brand_name')->nullable()->after('resolved_brand_id');
            $table->string('resolution_state', 16)->nullable()->after('resolved_brand_name');
            $table->json('resolution')->nullable()->after('resolution_state');

            $table->index('resolved_brand_name');
            $table->index('resolution_state');
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->table('distributor_catalog_proposals', function (Blueprint $table) {
            $table->dropIndex(['resolved_brand_name']);
            $table->dropIndex(['resolution_state']);
            $table->dropColumn(['resolved_brand_id', 'resolved_brand_name', 'resolution_state', 'resolution']);
        });
    }
};

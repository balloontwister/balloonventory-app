<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Free-text reviewer note on a proposal. Captures the reasoning behind a manual
 * mapping ("for this distributor Color is a coarse family — prefer the title
 * shade") so it can be banked onto the learned alias and fed to the Phase 2 LLM
 * matcher. Lives on the relocatable `distributors` connection like the rest of
 * the proposal data.
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
            $table->text('note')->nullable()->after('proposed_warehouse_sku');
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->table('distributor_catalog_proposals', function (Blueprint $table) {
            $table->dropColumn('note');
        });
    }
};

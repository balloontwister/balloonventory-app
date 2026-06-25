<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a reviewer pin a proposal's packaging type (Nozzle Up / Loose / Retail …)
 * in the Edit modal, mirroring proposed_brand/size/color. Nullable, no DB-level
 * FK (the relocatable `distributors` connection holds no FKs); app code enforces
 * it against packaging_types.
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
            $table->char('proposed_packaging_id', 36)->nullable()->after('proposed_color_id');
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->table('distributor_catalog_proposals', function (Blueprint $table) {
            $table->dropColumn('proposed_packaging_id');
        });
    }
};

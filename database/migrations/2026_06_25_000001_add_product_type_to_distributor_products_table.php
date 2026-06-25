<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records the catalog product type a staged listing was classified as
 * (solid_latex, foil, printed, plastic, assortment, accessory, non_balloon).
 * Only solid_latex currently flows through to proposals; the rest stay parked in
 * staging with their attributes, indexed so the "deferred by type" counts and the
 * cluster gate are cheap.
 *
 * Lives on the relocatable `distributors` connection like the rest of staging.
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
            $table->string('product_type', 32)->nullable()->after('title');
            $table->index('product_type');
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->table('distributor_products', function (Blueprint $table) {
            $table->dropIndex(['product_type']);
            $table->dropColumn('product_type');
        });
    }
};

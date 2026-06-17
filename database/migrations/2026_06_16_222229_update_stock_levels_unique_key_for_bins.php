<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_levels', function (Blueprint $table) {
            // A SKU can now hold stock in more than one bin, so uniqueness must
            // include bin_id. Existing rows already point at the Default bin, so
            // widening the key is non-destructive.
            $table->dropUnique(['business_id', 'sku_id']);
            $table->unique(['business_id', 'sku_id', 'bin_id']);
        });
    }

    public function down(): void
    {
        Schema::table('stock_levels', function (Blueprint $table) {
            $table->dropUnique(['business_id', 'sku_id', 'bin_id']);
            $table->unique(['business_id', 'sku_id']);
        });
    }
};

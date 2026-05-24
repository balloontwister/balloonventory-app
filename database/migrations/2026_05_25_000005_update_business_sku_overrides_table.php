<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_sku_overrides', function (Blueprint $table) {
            // Reorder threshold moved to Favorites list_item.planned_quantity.
            // is_hidden superseded by soft-deleting the stock_level row.
            $table->dropColumn(['reorder_threshold', 'is_hidden']);
        });
    }

    public function down(): void
    {
        Schema::table('business_sku_overrides', function (Blueprint $table) {
            $table->decimal('reorder_threshold', 10, 2)->nullable();
            $table->boolean('is_hidden')->default(false);
        });
    }
};

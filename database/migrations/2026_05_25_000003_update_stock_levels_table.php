<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_levels', function (Blueprint $table) {
            // Replace single decimal quantity with two integer bag counters.
            $table->dropColumn('quantity');
            $table->integer('full_bags')->default(0)->after('sku_id');
            $table->integer('open_bags')->default(0)->after('full_bags');

            // Every stock_level row belongs to a bin (NOT NULL; default bin always exists).
            $table->char('bin_id', 36)->after('open_bags');
            $table->foreign('bin_id')->references('id')->on('bins');
            $table->index('bin_id');

            // Soft deletes: "Remove from Inventory" soft-deletes the row and writes a movement log.
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('stock_levels', function (Blueprint $table) {
            $table->dropForeign(['bin_id']);
            $table->dropIndex(['bin_id']);
            $table->dropColumn(['full_bags', 'open_bags', 'bin_id', 'deleted_at']);
            $table->decimal('quantity', 10, 2)->default(0);
        });
    }
};

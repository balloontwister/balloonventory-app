<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_levels', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('business_id', 36);
            $table->char('sku_id', 36);
            $table->decimal('quantity', 10, 2)->default(0);
            $table->timestamp('last_movement_at')->nullable();
            $table->timestamps();
            // No softDeletes — quantity 0 is the empty state.

            $table->foreign('business_id')->references('id')->on('businesses');
            $table->foreign('sku_id')->references('id')->on('skus');

            $table->index('business_id');
            $table->index('sku_id');
            $table->unique(['business_id', 'sku_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_levels');
    }
};

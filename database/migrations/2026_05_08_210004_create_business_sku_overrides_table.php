<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_sku_overrides', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('business_id', 36);
            $table->char('sku_id', 36);
            $table->string('custom_name')->nullable();
            $table->string('custom_color_hex', 7)->nullable();
            $table->decimal('reorder_threshold', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_hidden')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('business_id')->references('id')->on('businesses');
            $table->foreign('sku_id')->references('id')->on('skus');

            $table->index('business_id');
            $table->index('sku_id');
            $table->unique(['business_id', 'sku_id', 'deleted_at'], 'bso_business_sku_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_sku_overrides');
    }
};

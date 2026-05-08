<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skus', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('name');
            $table->char('brand_id', 36);
            $table->string('size');
            $table->string('color_name')->nullable();
            $table->string('color_hex', 7)->nullable();
            $table->enum('finish', ['matte', 'standard', 'metallic', 'chrome', 'pearl', 'confetti', 'mosaic', 'agate'])->nullable();
            $table->integer('default_count_per_bag')->nullable();
            $table->string('manufacturer_sku')->nullable();
            $table->string('price_code')->nullable();
            $table->string('image_url')->nullable();
            $table->char('owned_by_business_id', 36)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('brand_id')->references('id')->on('brands');
            $table->foreign('owned_by_business_id')->references('id')->on('businesses');

            $table->index('brand_id');
            $table->index('owned_by_business_id');
            $table->index('price_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skus');
    }
};

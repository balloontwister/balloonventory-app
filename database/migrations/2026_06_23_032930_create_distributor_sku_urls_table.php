<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('distributor_sku_urls', function (Blueprint $table) {
            $table->char('distributor_id', 36);
            $table->char('sku_id', 36);
            $table->string('url', 2048);
            $table->decimal('price', 10, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->boolean('in_stock')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();

            $table->primary(['distributor_id', 'sku_id']);
            $table->foreign('distributor_id')->references('id')->on('distributors')->cascadeOnDelete();
            $table->foreign('sku_id')->references('id')->on('skus')->cascadeOnDelete();

            $table->index('sku_id');
            $table->index('last_checked_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distributor_sku_urls');
    }
};

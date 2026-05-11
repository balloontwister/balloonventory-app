<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sku_themes', function (Blueprint $table) {
            $table->char('sku_id', 36);
            $table->char('theme_id', 36);

            $table->primary(['sku_id', 'theme_id']);

            $table->foreign('sku_id')->references('id')->on('skus')->cascadeOnDelete();
            $table->foreign('theme_id')->references('id')->on('themes')->cascadeOnDelete();

            $table->index('theme_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sku_themes');
    }
};

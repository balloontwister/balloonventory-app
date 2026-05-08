<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('list_items', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('list_id', 36);
            $table->char('sku_id', 36);
            $table->decimal('planned_quantity', 10, 2)->nullable();
            $table->integer('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('list_id')->references('id')->on('lists');
            $table->foreign('sku_id')->references('id')->on('skus');

            $table->index('list_id');
            $table->index('sku_id');
            $table->unique(['list_id', 'sku_id', 'deleted_at'], 'li_list_sku_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('list_items');
    }
};

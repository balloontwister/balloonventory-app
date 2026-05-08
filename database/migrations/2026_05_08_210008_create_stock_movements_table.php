<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('business_id', 36);
            $table->char('sku_id', 36);
            $table->char('user_id', 36);
            $table->enum('direction', ['in', 'out']);
            $table->decimal('quantity_change', 10, 2);
            $table->string('upc_scanned')->nullable();
            $table->char('job_id', 36)->nullable();
            $table->text('notes')->nullable();
            // Append-only: no updated_at, no deleted_at.
            $table->timestamp('created_at');

            $table->foreign('business_id')->references('id')->on('businesses');
            $table->foreign('sku_id')->references('id')->on('skus');
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('job_id')->references('id')->on('jobs');

            $table->index('business_id');
            $table->index('sku_id');
            $table->index('user_id');
            $table->index('job_id');
            $table->index(['business_id', 'sku_id', 'created_at'], 'sm_business_sku_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};

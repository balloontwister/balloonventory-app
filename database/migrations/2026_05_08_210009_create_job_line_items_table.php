<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_line_items', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('job_id', 36);
            $table->char('sku_id', 36);
            $table->decimal('planned_quantity', 10, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('job_id')->references('id')->on('jobs');
            $table->foreign('sku_id')->references('id')->on('skus');

            $table->index('job_id');
            $table->index('sku_id');
            $table->unique(['job_id', 'sku_id', 'deleted_at'], 'jli_job_sku_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_line_items');
    }
};

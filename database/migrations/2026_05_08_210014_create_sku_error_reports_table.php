<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sku_error_reports', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('sku_id', 36);
            $table->char('reported_by_user_id', 36);
            $table->char('reported_from_business_id', 36)->nullable();
            $table->text('description');
            $table->enum('status', ['open', 'acknowledged', 'fixed', 'rejected'])->default('open');
            $table->char('resolved_by_user_id', 36)->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('sku_id')->references('id')->on('skus');
            $table->foreign('reported_by_user_id')->references('id')->on('users');
            $table->foreign('reported_from_business_id')->references('id')->on('businesses');
            $table->foreign('resolved_by_user_id')->references('id')->on('users');

            $table->index('sku_id');
            $table->index('reported_by_user_id');
            $table->index('reported_from_business_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sku_error_reports');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_upc_scans', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('business_id', 36);
            $table->string('upc_string');
            $table->enum('direction', ['in', 'out']);
            $table->decimal('quantity_scanned', 10, 2)->default(1);
            $table->char('scanned_by_user_id', 36);
            $table->timestamp('scanned_at');
            $table->enum('status', ['pending', 'resolved_assigned', 'resolved_created', 'rejected'])->default('pending');
            $table->char('resolved_by_user_id', 36)->nullable();
            $table->char('resolved_to_sku_id', 36)->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('business_id')->references('id')->on('businesses');
            $table->foreign('scanned_by_user_id')->references('id')->on('users');
            $table->foreign('resolved_by_user_id')->references('id')->on('users');
            $table->foreign('resolved_to_sku_id')->references('id')->on('skus');

            $table->index('business_id');
            $table->index('upc_string');
            $table->index('scanned_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_upc_scans');
    }
};

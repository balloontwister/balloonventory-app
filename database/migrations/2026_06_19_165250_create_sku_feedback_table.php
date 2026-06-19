<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * User-submitted "feedback on this item" reports: a field-targeted edit or error
 * report raised from the inventory SKU detail page when the physical product
 * doesn't match our catalog data. Like barcode_link_audits, this is NOT
 * tenant-scoped — feedback concerns the shared catalog, so admins review it
 * across all businesses. business_id/user_id are kept only as context for who
 * reported it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sku_feedback', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('business_id', 36)->nullable()->index();
            $table->char('user_id', 36)->nullable()->index();
            $table->char('sku_id', 36)->index();
            $table->string('sku_name'); // snapshot of the product at report time
            $table->string('field', 32); // flagged attribute key (name|color|barcode|…)
            $table->string('current_value')->nullable(); // what the system showed
            $table->string('suggested_value')->nullable(); // what the user says it should be
            $table->text('note')->nullable();
            $table->string('status', 16)->default('open')->index();
            $table->char('resolved_by_user_id', 36)->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sku_feedback');
    }
};

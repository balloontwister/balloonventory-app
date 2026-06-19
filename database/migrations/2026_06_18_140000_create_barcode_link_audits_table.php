<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only audit log of barcodes linked to catalog SKUs from the scan page.
 * Because any business user can write a manufacturer barcode straight onto a
 * shared catalog row, this gives admins a trail of who linked what, when —
 * so a wrong or malicious link can be spotted and corrected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barcode_link_audits', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('business_id', 36)->nullable()->index();
            $table->char('user_id', 36)->nullable()->index();
            $table->char('sku_id', 36)->index();
            $table->string('sku_name');
            $table->string('barcode', 32);
            $table->string('field', 8); // 'upc' | 'ean'
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barcode_link_audits');
    }
};

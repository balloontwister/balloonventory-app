<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Distinguishes how a barcode link was recorded: a business user on the scan page
 * (`scan`, the default and all existing rows) vs an admin mapping a distributor
 * proposal to an existing catalog SKU (`admin`, with a null business_id since it
 * acts on the shared catalog, not a tenant).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('barcode_link_audits', function (Blueprint $table) {
            $table->string('source', 20)->default('scan')->after('field');
        });
    }

    public function down(): void
    {
        Schema::table('barcode_link_audits', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};

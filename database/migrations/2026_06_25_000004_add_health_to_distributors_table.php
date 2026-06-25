<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extraction health for drift detection: each crawl judges how many pages still
 * parse against the distributor's recipe and records a status here, so a silent
 * template change surfaces as a flag instead of garbage proposals.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('distributors', function (Blueprint $table) {
            $table->string('health_status', 20)->nullable()->after('last_synced_at');
            $table->timestamp('health_checked_at')->nullable()->after('health_status');
            $table->string('health_detail')->nullable()->after('health_checked_at');
        });
    }

    public function down(): void
    {
        Schema::table('distributors', function (Blueprint $table) {
            $table->dropColumn(['health_status', 'health_checked_at', 'health_detail']);
        });
    }
};

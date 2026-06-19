<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Let admins revert a barcode link from the log. Rather than deleting the audit
 * row (it must stay for accountability), reverting stamps it: who undid it and
 * when. A reverted row is shown struck-through with a "Reverted" badge.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('barcode_link_audits', function (Blueprint $table) {
            $table->timestamp('reverted_at')->nullable()->after('field');
            $table->char('reverted_by_user_id', 36)->nullable()->after('reverted_at');
        });
    }

    public function down(): void
    {
        Schema::table('barcode_link_audits', function (Blueprint $table) {
            $table->dropColumn(['reverted_at', 'reverted_by_user_id']);
        });
    }
};

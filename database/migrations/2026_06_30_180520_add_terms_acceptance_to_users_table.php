<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // When the user accepted the Terms/Privacy, and which version
            // (config('legal.terms_version')). Null = never accepted; a value
            // older than the current version triggers re-acceptance.
            $table->timestamp('terms_accepted_at')->nullable()->after('email_verified_at');
            $table->string('terms_version')->nullable()->after('terms_accepted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['terms_accepted_at', 'terms_version']);
        });
    }
};

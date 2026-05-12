<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // BCP-47 short tag (e.g. 'en', 'es', 'pt-BR'). 8 chars covers
            // every realistic variant.
            $table->string('locale', 8)->default('en')->after('is_super_admin');

            // IANA tz database identifier (e.g. 'America/Chicago'). 64 chars
            // is the longest known identifier today; leave headroom.
            $table->string('timezone', 64)->nullable()->after('locale');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['locale', 'timezone']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sizes', function (Blueprint $table) {
            // Alternate imperial name — the second US/EU label for sizes that
            // ship under two names but are the same balloon (e.g. 11" / 12").
            $table->string('alt_imperial_name')->nullable()->after('name');

            // Canonical metric diameter for round latex sizes. Modeling sizes
            // (260, 350, etc.) leave this NULL because the name already encodes
            // the spec (inflated diameter × length in inches).
            $table->unsignedSmallInteger('diameter_cm')->nullable()->after('alt_imperial_name');
        });
    }

    public function down(): void
    {
        Schema::table('sizes', function (Blueprint $table) {
            $table->dropColumn(['alt_imperial_name', 'diameter_cm']);
        });
    }
};

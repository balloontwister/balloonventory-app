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
        Schema::table('businesses', function (Blueprint $table) {
            // Why the business is frozen — see App\Enums\BusinessFrozenReason.
            // Null whenever frozen_at is null (i.e. the business is active).
            $table->string('frozen_reason')->nullable()->after('frozen_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn('frozen_reason');
        });
    }
};

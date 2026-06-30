<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('color', 7)->default('#6366F1')->after('logo_path');
        });

        // Preserve colors people already chose: seed each business's color from an
        // owner's per-member badge color (the old source of truth), if present.
        $owners = DB::table('memberships')
            ->select('business_id', 'business_badge_color')
            ->where('role', 'owner')
            ->whereNull('deleted_at')
            ->whereNotNull('business_badge_color')
            ->get()
            ->keyBy('business_id');

        foreach ($owners as $businessId => $row) {
            DB::table('businesses')
                ->where('id', $businessId)
                ->update(['color' => $row->business_badge_color]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};

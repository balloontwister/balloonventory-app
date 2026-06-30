<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            // When true, the location's position is pinned: it can't be dragged
            // and other locations can't reorder past it. Mirrors bins.position_locked.
            $table->boolean('position_locked')->default(false)->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn('position_locked');
        });
    }
};

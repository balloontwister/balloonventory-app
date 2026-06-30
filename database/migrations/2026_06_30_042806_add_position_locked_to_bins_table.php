<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bins', function (Blueprint $table) {
            // When true, the bin's position in its location is pinned: it can't
            // be dragged, and reordering other bins can't move past it. Parallels
            // number_locked (which pins the printed number).
            $table->boolean('position_locked')->default(false)->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('bins', function (Blueprint $table) {
            $table->dropColumn('position_locked');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Make the distributors uniqueness indexes soft-delete aware, mirroring
 * 2026_06_06_133000 for skus/brands.
 *
 * DistributorController's validation excludes trashed rows (whereNull
 * 'deleted_at') so a soft-deleted name/slug can be reused, and DistributorSeeder
 * firstOrCreate()s by slug. But the plain single-column uniques still contain
 * the trashed rows, so recreating a distributor with a trashed one's slug/name
 * collided on the index and surfaced as a 500 instead of working.
 *
 * Folding deleted_at into the tuple lets a trashed row and a live row share a
 * value. Live-row uniqueness is enforced by the application validation rules
 * (NULLs are distinct inside a unique index on both MySQL and SQLite).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('distributors', function (Blueprint $table) {
            $table->dropUnique('distributors_name_unique');
            $table->dropUnique('distributors_slug_unique');
            $table->unique(['name', 'deleted_at']);
            $table->unique(['slug', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::table('distributors', function (Blueprint $table) {
            $table->dropUnique(['name', 'deleted_at']);
            $table->dropUnique(['slug', 'deleted_at']);
            $table->unique('name');
            $table->unique('slug');
        });
    }
};

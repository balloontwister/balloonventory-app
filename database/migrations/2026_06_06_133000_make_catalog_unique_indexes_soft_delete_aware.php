<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Make the catalog uniqueness indexes soft-delete aware.
 *
 * The validation rules for skus.upc and brands.name/abbreviation exclude
 * trashed rows (whereNull('deleted_at')) on purpose — the intent is that a
 * soft-deleted value can be reused. But the backing indexes were plain
 * single-column uniques that still contain soft-deleted rows, so recreating a
 * SKU/brand with a trashed row's value collided on the index and surfaced as a
 * 500 instead of a clean validation error.
 *
 * Folding deleted_at into the unique tuple lets a trashed row (deleted_at set)
 * and a live row (deleted_at NULL) coexist with the same value, matching what
 * the colors table already does (unique(['name','brand_id','deleted_at'])).
 *
 * Caveat: both MySQL and SQLite treat NULLs as distinct inside a unique index,
 * so this composite no longer guarantees live-row uniqueness at the DB layer
 * (two rows with deleted_at = NULL are allowed). Live-row uniqueness is — and
 * already was, for colors — enforced by the application validation rules. The
 * DB index now only backstops exact (value, deleted_at) collisions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('skus', function (Blueprint $table) {
            $table->dropUnique('skus_upc_unique');
            $table->unique(['upc', 'deleted_at']);
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->dropUnique('brands_name_unique');
            $table->dropUnique('brands_abbreviation_unique');
            $table->unique(['name', 'deleted_at']);
            $table->unique(['abbreviation', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::table('skus', function (Blueprint $table) {
            $table->dropUnique(['upc', 'deleted_at']);
            $table->unique('upc');
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->dropUnique(['name', 'deleted_at']);
            $table->dropUnique(['abbreviation', 'deleted_at']);
            $table->unique('name');
            $table->unique('abbreviation');
        });
    }
};

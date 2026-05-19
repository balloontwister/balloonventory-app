<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A balloon_size is brand+material+size+shape. Without shape, a "6-inch heart"
 * and a "6-inch round" collapse into one row. Existing rows are backfilled to
 * Round (the seeded default), then the column is tightened to NOT NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('balloon_sizes', function (Blueprint $table) {
            $table->char('shape_id', 36)->nullable()->after('size_id');
        });

        if (DB::table('balloon_sizes')->count() > 0) {
            $roundId = DB::table('shapes')->where('name', 'Round')->value('id');
            if (! $roundId) {
                throw new RuntimeException(
                    'Cannot backfill balloon_sizes.shape_id: "Round" shape not found. '.
                    'Seed ShapeSeeder before running this migration.'
                );
            }
            DB::table('balloon_sizes')->update(['shape_id' => $roundId]);
        }

        Schema::table('balloon_sizes', function (Blueprint $table) {
            $table->char('shape_id', 36)->nullable(false)->change();
            $table->foreign('shape_id')->references('id')->on('shapes');
            $table->index('shape_id');
        });
    }

    public function down(): void
    {
        Schema::table('balloon_sizes', function (Blueprint $table) {
            $table->dropForeign(['shape_id']);
            $table->dropIndex(['shape_id']);
            $table->dropColumn('shape_id');
        });
    }
};

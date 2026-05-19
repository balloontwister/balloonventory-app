<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the redundant `size_category` enum from the `sizes` table. The
 * `sizes` table now stands alone as the brand-agnostic "size family" — the
 * same pattern textures and colors already follow (texture → texture_family,
 * color → color_family, balloon_size → size). The broader grouping the enum
 * provided is no longer needed: with only ~12 size rows, sort_order is enough.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sizes', function (Blueprint $table) {
            $table->dropIndex(['size_category']);
            $table->dropColumn('size_category');
        });
    }

    public function down(): void
    {
        Schema::table('sizes', function (Blueprint $table) {
            // Restored as nullable: original row values cannot be recovered.
            $table->enum('size_category', ['small', 'medium', 'large', 'giant', 'small_modeling', 'large_modeling'])
                ->nullable()
                ->after('diameter_cm');
            $table->index('size_category');
        });
    }
};

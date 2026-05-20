<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Enforce that every color must belong to a brand and have a texture.
        // All 132 existing rows already have values — this is a constraint tightening.
        Schema::table('colors', function (Blueprint $table) {
            $table->char('brand_id', 36)->nullable(false)->change();
            $table->char('texture_id', 36)->nullable(false)->change();
        });

        // Remove denormalized columns from skus. Texture is now derived from
        // color.texture_id and shape from balloon_size.shape_id.
        Schema::table('skus', function (Blueprint $table) {
            $table->dropForeign(['texture_id']);
            $table->dropIndex(['texture_id']);
            $table->dropColumn('texture_id');
            $table->dropForeign(['shape_id']);
            $table->dropIndex(['shape_id']);
            $table->dropColumn('shape_id');
        });
    }

    public function down(): void
    {
        Schema::table('colors', function (Blueprint $table) {
            $table->char('brand_id', 36)->nullable()->change();
            $table->char('texture_id', 36)->nullable()->change();
        });

        Schema::table('skus', function (Blueprint $table) {
            $table->char('shape_id', 36)->nullable()->after('balloon_size_id');
            $table->foreign('shape_id')->references('id')->on('shapes');
            $table->char('texture_id', 36)->nullable()->after('shape_id');
            $table->foreign('texture_id')->references('id')->on('textures');
        });
    }
};

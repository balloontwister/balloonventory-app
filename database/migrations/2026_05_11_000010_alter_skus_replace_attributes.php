<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('skus', function (Blueprint $table) {
            // Remove the old free-text and enum attribute columns.
            $table->dropColumn(['size', 'finish', 'color_name', 'color_hex']);
        });

        Schema::table('skus', function (Blueprint $table) {
            // Add proper FK lookups for each attribute. All nullable because
            // conditional attribute validation (e.g. foil SKUs have no size)
            // is deferred to a later phase.
            $table->char('size_id', 36)->nullable()->after('brand_id');
            $table->char('shape_id', 36)->nullable()->after('size_id');
            $table->char('texture_id', 36)->nullable()->after('shape_id');
            $table->char('color_id', 36)->nullable()->after('texture_id');
            $table->char('material_id', 36)->nullable()->after('color_id');
            $table->boolean('is_printed')->default(false)->after('material_id');

            $table->foreign('size_id')->references('id')->on('sizes');
            $table->foreign('shape_id')->references('id')->on('shapes');
            $table->foreign('texture_id')->references('id')->on('textures');
            $table->foreign('color_id')->references('id')->on('colors');
            $table->foreign('material_id')->references('id')->on('materials');

            $table->index('size_id');
            $table->index('shape_id');
            $table->index('texture_id');
            $table->index('color_id');
            $table->index('material_id');
            $table->index('is_printed');
        });
    }

    public function down(): void
    {
        Schema::table('skus', function (Blueprint $table) {
            $table->dropForeign(['size_id', 'shape_id', 'texture_id', 'color_id', 'material_id']);
            $table->dropIndex(['size_id', 'shape_id', 'texture_id', 'color_id', 'material_id', 'is_printed']);
            $table->dropColumn(['size_id', 'shape_id', 'texture_id', 'color_id', 'material_id', 'is_printed']);
        });

        Schema::table('skus', function (Blueprint $table) {
            $table->string('size')->after('brand_id');
            $table->string('color_name')->nullable()->after('size');
            $table->string('color_hex', 7)->nullable()->after('color_name');
            $table->enum('finish', ['matte', 'standard', 'metallic', 'chrome', 'pearl', 'confetti', 'mosaic', 'agate'])->nullable()->after('color_hex');
        });
    }
};

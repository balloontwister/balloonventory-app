<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The production database was created on a host whose default storage engine
 * is MyISAM. MyISAM silently discards every FOREIGN KEY declaration at
 * CREATE TABLE time, so the FKs every prior migration declared do not exist
 * in production at all. This migration:
 *
 *   1. Converts every MyISAM table in the schema to InnoDB.
 *   2. Re-asserts every FK the original migrations declared.
 *
 * Idempotent on MySQL via dropForeignIfExists. Skipped entirely on SQLite,
 * which honors FK declarations from the original create migrations.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $myisamTables = DB::select(
            "SELECT TABLE_NAME FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND ENGINE = 'MyISAM'"
        );

        foreach ($myisamTables as $row) {
            DB::statement("ALTER TABLE `{$row->TABLE_NAME}` ENGINE=InnoDB");
        }

        $this->restoreForeignKeys();
    }

    public function down(): void
    {
        // Intentionally a no-op. Reverting to MyISAM would lose every FK
        // constraint and re-introduce the original key-length and concurrency
        // problems. If a rollback is ever truly needed, do it manually.
    }

    private function restoreForeignKeys(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->dropForeignIfExists(['user_id']);
            $table->foreign('user_id')->references('id')->on('users');
            $table->dropForeignIfExists(['business_id']);
            $table->foreign('business_id')->references('id')->on('businesses');
        });

        Schema::table('skus', function (Blueprint $table) {
            $table->dropForeignIfExists(['brand_id']);
            $table->foreign('brand_id')->references('id')->on('brands');
            $table->dropForeignIfExists(['owned_by_business_id']);
            $table->foreign('owned_by_business_id')->references('id')->on('businesses');
            $table->dropForeignIfExists(['shape_id']);
            $table->foreign('shape_id')->references('id')->on('shapes');
            $table->dropForeignIfExists(['texture_id']);
            $table->foreign('texture_id')->references('id')->on('textures');
            $table->dropForeignIfExists(['color_id']);
            $table->foreign('color_id')->references('id')->on('colors');
            $table->dropForeignIfExists(['material_id']);
            $table->foreign('material_id')->references('id')->on('materials');
            $table->dropForeignIfExists(['balloon_size_id']);
            $table->foreign('balloon_size_id')->references('id')->on('balloon_sizes');
            $table->dropForeignIfExists(['packaging_id']);
            $table->foreign('packaging_id')->references('id')->on('packaging_types');
            $table->dropForeignIfExists(['price_code_id']);
            $table->foreign('price_code_id')->references('id')->on('price_codes');
        });

        Schema::table('shapes', function (Blueprint $table) {
            $table->dropForeignIfExists(['material_id']);
            $table->foreign('material_id')->references('id')->on('materials');
        });

        Schema::table('textures', function (Blueprint $table) {
            $table->dropForeignIfExists(['material_id']);
            $table->foreign('material_id')->references('id')->on('materials');
            $table->dropForeignIfExists(['brand_id']);
            $table->foreign('brand_id')->references('id')->on('brands');
            $table->dropForeignIfExists(['texture_family_id']);
            $table->foreign('texture_family_id')->references('id')->on('texture_families');
        });

        Schema::table('color_families', function (Blueprint $table) {
            $table->dropForeignIfExists(['material_id']);
            $table->foreign('material_id')->references('id')->on('materials');
        });

        Schema::table('colors', function (Blueprint $table) {
            $table->dropForeignIfExists(['color_family_id']);
            $table->foreign('color_family_id')->references('id')->on('color_families');
            $table->dropForeignIfExists(['brand_id']);
            $table->foreign('brand_id')->references('id')->on('brands');
            $table->dropForeignIfExists(['material_id']);
            $table->foreign('material_id')->references('id')->on('materials');
            $table->dropForeignIfExists(['texture_id']);
            $table->foreign('texture_id')->references('id')->on('textures');
        });

        Schema::table('balloon_sizes', function (Blueprint $table) {
            $table->dropForeignIfExists(['brand_id']);
            $table->foreign('brand_id')->references('id')->on('brands');
            $table->dropForeignIfExists(['material_id']);
            $table->foreign('material_id')->references('id')->on('materials');
            $table->dropForeignIfExists(['size_id']);
            $table->foreign('size_id')->references('id')->on('sizes');
        });

        Schema::table('price_codes', function (Blueprint $table) {
            $table->dropForeignIfExists(['brand_id']);
            $table->foreign('brand_id')->references('id')->on('brands');
        });

        Schema::table('brand_gs1_prefixes', function (Blueprint $table) {
            $table->dropForeignIfExists(['brand_id']);
            $table->foreign('brand_id')->references('id')->on('brands');
        });

        Schema::table('sku_themes', function (Blueprint $table) {
            $table->dropForeignIfExists(['sku_id']);
            $table->foreign('sku_id')->references('id')->on('skus')->cascadeOnDelete();
            $table->dropForeignIfExists(['theme_id']);
            $table->foreign('theme_id')->references('id')->on('themes')->cascadeOnDelete();
        });

        Schema::table('sku_print_colors', function (Blueprint $table) {
            $table->dropForeignIfExists(['sku_id']);
            $table->foreign('sku_id')->references('id')->on('skus')->cascadeOnDelete();
            $table->dropForeignIfExists(['print_color_id']);
            $table->foreign('print_color_id')->references('id')->on('print_colors')->cascadeOnDelete();
        });

        Schema::table('sku_print_sides', function (Blueprint $table) {
            $table->dropForeignIfExists(['sku_id']);
            $table->foreign('sku_id')->references('id')->on('skus')->cascadeOnDelete();
            $table->dropForeignIfExists(['print_side_id']);
            $table->foreign('print_side_id')->references('id')->on('print_sides')->cascadeOnDelete();
        });

        Schema::table('identical_skus', function (Blueprint $table) {
            $table->dropForeignIfExists(['sku_id']);
            $table->foreign('sku_id')->references('id')->on('skus')->cascadeOnDelete();
            $table->dropForeignIfExists(['identical_sku_id']);
            $table->foreign('identical_sku_id')->references('id')->on('skus')->cascadeOnDelete();
        });

        Schema::table('business_sku_overrides', function (Blueprint $table) {
            $table->dropForeignIfExists(['business_id']);
            $table->foreign('business_id')->references('id')->on('businesses');
            $table->dropForeignIfExists(['sku_id']);
            $table->foreign('sku_id')->references('id')->on('skus');
        });

        Schema::table('material_translations', function (Blueprint $table) {
            $table->dropForeignIfExists(['material_id']);
            $table->foreign('material_id')->references('id')->on('materials')->cascadeOnDelete();
        });

        Schema::table('shape_translations', function (Blueprint $table) {
            $table->dropForeignIfExists(['shape_id']);
            $table->foreign('shape_id')->references('id')->on('shapes')->cascadeOnDelete();
        });

        Schema::table('texture_translations', function (Blueprint $table) {
            $table->dropForeignIfExists(['texture_id']);
            $table->foreign('texture_id')->references('id')->on('textures')->cascadeOnDelete();
        });

        Schema::table('color_family_translations', function (Blueprint $table) {
            $table->dropForeignIfExists(['color_family_id']);
            $table->foreign('color_family_id')->references('id')->on('color_families')->cascadeOnDelete();
        });

        Schema::table('color_translations', function (Blueprint $table) {
            $table->dropForeignIfExists(['color_id']);
            $table->foreign('color_id')->references('id')->on('colors')->cascadeOnDelete();
        });

        Schema::table('theme_translations', function (Blueprint $table) {
            $table->dropForeignIfExists(['theme_id']);
            $table->foreign('theme_id')->references('id')->on('themes')->cascadeOnDelete();
        });

        Schema::table('jobs', function (Blueprint $table) {
            $table->dropForeignIfExists(['business_id']);
            $table->foreign('business_id')->references('id')->on('businesses');
            $table->dropForeignIfExists(['created_by_user_id']);
            $table->foreign('created_by_user_id')->references('id')->on('users');
        });

        Schema::table('job_line_items', function (Blueprint $table) {
            $table->dropForeignIfExists(['job_id']);
            $table->foreign('job_id')->references('id')->on('jobs');
            $table->dropForeignIfExists(['sku_id']);
            $table->foreign('sku_id')->references('id')->on('skus');
        });

        Schema::table('lists', function (Blueprint $table) {
            $table->dropForeignIfExists(['business_id']);
            $table->foreign('business_id')->references('id')->on('businesses');
            $table->dropForeignIfExists(['created_by_user_id']);
            $table->foreign('created_by_user_id')->references('id')->on('users');
        });

        Schema::table('list_items', function (Blueprint $table) {
            $table->dropForeignIfExists(['list_id']);
            $table->foreign('list_id')->references('id')->on('lists');
            $table->dropForeignIfExists(['sku_id']);
            $table->foreign('sku_id')->references('id')->on('skus');
        });

        Schema::table('local_prices', function (Blueprint $table) {
            $table->dropForeignIfExists(['business_id']);
            $table->foreign('business_id')->references('id')->on('businesses');
        });

        Schema::table('stock_levels', function (Blueprint $table) {
            $table->dropForeignIfExists(['business_id']);
            $table->foreign('business_id')->references('id')->on('businesses');
            $table->dropForeignIfExists(['sku_id']);
            $table->foreign('sku_id')->references('id')->on('skus');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeignIfExists(['business_id']);
            $table->foreign('business_id')->references('id')->on('businesses');
            $table->dropForeignIfExists(['sku_id']);
            $table->foreign('sku_id')->references('id')->on('skus');
            $table->dropForeignIfExists(['user_id']);
            $table->foreign('user_id')->references('id')->on('users');
            $table->dropForeignIfExists(['job_id']);
            $table->foreign('job_id')->references('id')->on('jobs');
        });

        Schema::table('pending_upc_scans', function (Blueprint $table) {
            $table->dropForeignIfExists(['business_id']);
            $table->foreign('business_id')->references('id')->on('businesses');
            $table->dropForeignIfExists(['scanned_by_user_id']);
            $table->foreign('scanned_by_user_id')->references('id')->on('users');
            $table->dropForeignIfExists(['resolved_by_user_id']);
            $table->foreign('resolved_by_user_id')->references('id')->on('users');
            $table->dropForeignIfExists(['resolved_to_sku_id']);
            $table->foreign('resolved_to_sku_id')->references('id')->on('skus');
        });

        Schema::table('sku_error_reports', function (Blueprint $table) {
            $table->dropForeignIfExists(['sku_id']);
            $table->foreign('sku_id')->references('id')->on('skus');
            $table->dropForeignIfExists(['reported_by_user_id']);
            $table->foreign('reported_by_user_id')->references('id')->on('users');
            $table->dropForeignIfExists(['reported_from_business_id']);
            $table->foreign('reported_from_business_id')->references('id')->on('businesses');
            $table->dropForeignIfExists(['resolved_by_user_id']);
            $table->foreign('resolved_by_user_id')->references('id')->on('users');
        });
    }
};

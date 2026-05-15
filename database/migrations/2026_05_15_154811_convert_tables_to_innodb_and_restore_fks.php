<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The production database was created on a host whose default storage engine
 * is MyISAM. MyISAM silently discards every FOREIGN KEY declaration at
 * CREATE TABLE time, so the FKs every prior migration declared do not exist
 * in production at all. This migration:
 *
 *   1. Converts every MyISAM table to InnoDB.
 *   2. Re-asserts every FK the original migrations declared.
 *
 * Idempotent on MySQL: each FK is added only if it doesn't already exist,
 * so the migration is safe to run on either prod (where no FKs exist) or a
 * future fresh InnoDB install (where the original create migrations would
 * have established them). Skipped entirely on SQLite — the test env honors
 * FK declarations from the original create migrations.
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

        $this->addFk('memberships', 'user_id', 'users');
        $this->addFk('memberships', 'business_id', 'businesses');

        $this->addFk('skus', 'brand_id', 'brands');
        $this->addFk('skus', 'owned_by_business_id', 'businesses');
        $this->addFk('skus', 'shape_id', 'shapes');
        $this->addFk('skus', 'texture_id', 'textures');
        $this->addFk('skus', 'color_id', 'colors');
        $this->addFk('skus', 'material_id', 'materials');
        $this->addFk('skus', 'balloon_size_id', 'balloon_sizes');
        $this->addFk('skus', 'packaging_id', 'packaging_types');
        $this->addFk('skus', 'price_code_id', 'price_codes');

        $this->addFk('shapes', 'material_id', 'materials');

        $this->addFk('textures', 'material_id', 'materials');
        $this->addFk('textures', 'brand_id', 'brands');
        $this->addFk('textures', 'texture_family_id', 'texture_families');

        $this->addFk('color_families', 'material_id', 'materials');

        $this->addFk('colors', 'color_family_id', 'color_families');
        $this->addFk('colors', 'brand_id', 'brands');
        $this->addFk('colors', 'material_id', 'materials');
        $this->addFk('colors', 'texture_id', 'textures');

        $this->addFk('balloon_sizes', 'brand_id', 'brands');
        $this->addFk('balloon_sizes', 'material_id', 'materials');
        $this->addFk('balloon_sizes', 'size_id', 'sizes');

        $this->addFk('price_codes', 'brand_id', 'brands');

        $this->addFk('brand_gs1_prefixes', 'brand_id', 'brands');

        $this->addFk('sku_themes', 'sku_id', 'skus', onDelete: 'CASCADE');
        $this->addFk('sku_themes', 'theme_id', 'themes', onDelete: 'CASCADE');

        $this->addFk('sku_print_colors', 'sku_id', 'skus', onDelete: 'CASCADE');
        $this->addFk('sku_print_colors', 'print_color_id', 'print_colors', onDelete: 'CASCADE');

        $this->addFk('sku_print_sides', 'sku_id', 'skus', onDelete: 'CASCADE');
        $this->addFk('sku_print_sides', 'print_side_id', 'print_sides', onDelete: 'CASCADE');

        $this->addFk('identical_skus', 'sku_id', 'skus', onDelete: 'CASCADE');
        $this->addFk('identical_skus', 'identical_sku_id', 'skus', onDelete: 'CASCADE');

        $this->addFk('business_sku_overrides', 'business_id', 'businesses');
        $this->addFk('business_sku_overrides', 'sku_id', 'skus');

        $this->addFk('material_translations', 'material_id', 'materials', onDelete: 'CASCADE');
        $this->addFk('shape_translations', 'shape_id', 'shapes', onDelete: 'CASCADE');
        $this->addFk('texture_translations', 'texture_id', 'textures', onDelete: 'CASCADE');
        $this->addFk('color_family_translations', 'color_family_id', 'color_families', onDelete: 'CASCADE');
        $this->addFk('color_translations', 'color_id', 'colors', onDelete: 'CASCADE');
        $this->addFk('theme_translations', 'theme_id', 'themes', onDelete: 'CASCADE');

        $this->addFk('jobs', 'business_id', 'businesses');
        $this->addFk('jobs', 'created_by_user_id', 'users');

        $this->addFk('job_line_items', 'job_id', 'jobs');
        $this->addFk('job_line_items', 'sku_id', 'skus');

        $this->addFk('lists', 'business_id', 'businesses');
        $this->addFk('lists', 'created_by_user_id', 'users');

        $this->addFk('list_items', 'list_id', 'lists');
        $this->addFk('list_items', 'sku_id', 'skus');

        $this->addFk('local_prices', 'business_id', 'businesses');

        $this->addFk('stock_levels', 'business_id', 'businesses');
        $this->addFk('stock_levels', 'sku_id', 'skus');

        $this->addFk('stock_movements', 'business_id', 'businesses');
        $this->addFk('stock_movements', 'sku_id', 'skus');
        $this->addFk('stock_movements', 'user_id', 'users');
        $this->addFk('stock_movements', 'job_id', 'jobs');

        $this->addFk('pending_upc_scans', 'business_id', 'businesses');
        $this->addFk('pending_upc_scans', 'scanned_by_user_id', 'users');
        $this->addFk('pending_upc_scans', 'resolved_by_user_id', 'users');
        $this->addFk('pending_upc_scans', 'resolved_to_sku_id', 'skus');

        $this->addFk('sku_error_reports', 'sku_id', 'skus');
        $this->addFk('sku_error_reports', 'reported_by_user_id', 'users');
        $this->addFk('sku_error_reports', 'reported_from_business_id', 'businesses');
        $this->addFk('sku_error_reports', 'resolved_by_user_id', 'users');
    }

    public function down(): void
    {
        // Intentionally a no-op. Reverting to MyISAM would lose every FK
        // constraint and re-introduce the original key-length and concurrency
        // problems. If a rollback is ever truly needed, do it manually.
    }

    private function addFk(
        string $table,
        string $column,
        string $refTable,
        string $refColumn = 'id',
        ?string $onDelete = null,
    ): void {
        $fkName = "{$table}_{$column}_foreign";

        $exists = DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('TABLE_SCHEMA', DB::raw('DATABASE()'))
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $fkName)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();

        if ($exists) {
            return;
        }

        $sql = "ALTER TABLE `{$table}` ADD CONSTRAINT `{$fkName}` "
            ."FOREIGN KEY (`{$column}`) REFERENCES `{$refTable}` (`{$refColumn}`)";

        if ($onDelete !== null) {
            $sql .= " ON DELETE {$onDelete}";
        }

        DB::statement($sql);
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ──────────────────────────────────────────────
        // Step 1: New lookup tables
        // ──────────────────────────────────────────────

        Schema::create('texture_families', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('sort_order');
        });

        Schema::create('packaging_types', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('name')->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('sort_order');
        });

        Schema::create('price_codes', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('brand_id', 36);
            $table->string('code');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('brand_id')->references('id')->on('brands');
            $table->index('brand_id');
            $table->index(['brand_id', 'code']);
        });

        Schema::create('balloon_sizes', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('brand_id', 36);
            $table->char('material_id', 36);
            $table->char('size_id', 36);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('single_image_file_path')->nullable();
            $table->string('cluster_image_file_path')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('brand_id')->references('id')->on('brands');
            $table->foreign('material_id')->references('id')->on('materials');
            $table->foreign('size_id')->references('id')->on('sizes');

            $table->index('brand_id');
            $table->index('material_id');
            $table->index('size_id');
            $table->index('sort_order');
            $table->index(['brand_id', 'material_id', 'name']);
        });

        Schema::create('print_colors', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('name')->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('sort_order');
        });

        Schema::create('print_sides', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('name')->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('sort_order');
        });

        Schema::create('brand_gs1_prefixes', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('brand_id', 36);
            $table->string('prefix');
            $table->timestamps();

            $table->foreign('brand_id')->references('id')->on('brands');
            $table->index('brand_id');
            $table->unique(['brand_id', 'prefix']);
        });

        // ──────────────────────────────────────────────
        // Step 2: Add columns to existing reference tables
        // ──────────────────────────────────────────────

        Schema::table('brands', function (Blueprint $table) {
            $table->text('description')->nullable()->after('abbreviation');
            $table->string('url_1')->nullable()->after('description');
            $table->string('url_2')->nullable()->after('url_1');
            $table->string('logo_url')->nullable()->after('url_2');
            $table->string('primary_color_hex', 7)->nullable()->after('logo_url');
            $table->string('secondary_color_hex', 7)->nullable()->after('primary_color_hex');
            $table->boolean('is_active')->default(true)->after('secondary_color_hex');
        });

        // The original brands table had `brand_color_hex`; the spec replaces it
        // with separate primary/secondary fields. Copy any existing value into
        // primary_color_hex before dropping.
        if (Schema::hasColumn('brands', 'brand_color_hex')) {
            DB::statement('UPDATE brands SET primary_color_hex = brand_color_hex WHERE primary_color_hex IS NULL AND brand_color_hex IS NOT NULL');
            DB::statement('ALTER TABLE brands DROP COLUMN brand_color_hex');
        }

        Schema::table('materials', function (Blueprint $table) {
            $table->string('url')->nullable()->after('name');
            $table->string('image_path')->nullable()->after('url');
        });

        Schema::table('colors', function (Blueprint $table) {
            $table->char('material_id', 36)->nullable()->after('brand_id');
            $table->string('color_code')->nullable()->after('color_hex');
            $table->string('pms_value')->nullable()->after('color_code');
            $table->char('texture_id', 36)->nullable()->after('pms_value');
            $table->string('single_image_file_path')->nullable()->after('texture_id');
            $table->string('cluster_image_file_path')->nullable()->after('single_image_file_path');

            $table->foreign('material_id')->references('id')->on('materials');
            $table->foreign('texture_id')->references('id')->on('textures');

            $table->index('material_id');
            $table->index('texture_id');

            // Per the rework: uniqueness on (name, brand_id, material_id) is enforced
            // in seeders (firstOrCreate) and FormRequest validation, not at the DB level.
            // The NULL-aware DB approach doesn't work portably across MySQL/SQLite.
            $table->dropUnique(['name', 'brand_id', 'deleted_at']);
            $table->index(['name', 'brand_id', 'material_id']);
        });

        Schema::table('color_families', function (Blueprint $table) {
            $table->char('material_id', 36)->nullable()->after('name');
            $table->string('hex_color_start', 7)->nullable()->after('color_hex');
            $table->string('hex_color_end', 7)->nullable()->after('hex_color_start');
            $table->string('single_image_file_path')->nullable()->after('hex_color_end');
            $table->string('cluster_image_file_path')->nullable()->after('single_image_file_path');

            $table->foreign('material_id')->references('id')->on('materials');
            $table->index('material_id');

            $table->dropUnique(['name']);
            $table->index(['name', 'material_id']);
        });

        // Pre-existing color_hex represents a solid fallback (no gradient). Rename
        // for clarity since the spec uses hex_color_start/end for gradients.
        Schema::table('color_families', function (Blueprint $table) {
            $table->renameColumn('color_hex', 'fallback_color_hex');
        });

        Schema::table('textures', function (Blueprint $table) {
            $table->char('material_id', 36)->nullable()->after('name');
            $table->char('brand_id', 36)->nullable()->after('material_id');
            $table->char('texture_family_id', 36)->nullable()->after('texture_family');
            $table->string('image_path')->nullable()->after('texture_family_id');

            $table->foreign('material_id')->references('id')->on('materials');
            $table->foreign('brand_id')->references('id')->on('brands');
            $table->foreign('texture_family_id')->references('id')->on('texture_families');

            $table->index('material_id');
            $table->index('brand_id');
            $table->index('texture_family_id');

            $table->dropUnique(['name']);
            $table->index(['name', 'material_id', 'brand_id']);

            // Replace the free-text texture_family with the FK above.
            $table->dropIndex(['texture_family']);
            $table->dropColumn('texture_family');
        });

        Schema::table('shapes', function (Blueprint $table) {
            $table->char('material_id', 36)->nullable()->after('name');
            $table->string('image_path')->nullable()->after('material_id');

            $table->foreign('material_id')->references('id')->on('materials');
            $table->index('material_id');

            $table->dropUnique(['name']);
            $table->index(['name', 'material_id']);
        });

        Schema::table('sizes', function (Blueprint $table) {
            $table->string('single_image_file_path')->nullable()->after('diameter_cm');
            $table->string('cluster_image_file_path')->nullable()->after('single_image_file_path');
        });

        // ──────────────────────────────────────────────
        // Step 3: Rework skus table
        // ──────────────────────────────────────────────

        Schema::table('skus', function (Blueprint $table) {
            // Drop old FK constraints, indexes, and columns.
            $table->dropForeign(['size_id']);
            $table->dropIndex(['size_id']);
            $table->dropIndex(['price_code']);
            $table->dropIndex(['is_printed']);

            $table->dropColumn(['size_id', 'price_code', 'image_url', 'is_printed']);
        });

        Schema::table('skus', function (Blueprint $table) {
            // Rename manufacturer_sku → warehouse_sku.
            $table->renameColumn('manufacturer_sku', 'warehouse_sku');

            // Identifiers.
            $table->string('upc')->nullable()->after('warehouse_sku');
            $table->string('ean')->nullable()->after('upc');
            $table->string('asin')->nullable()->after('ean');
            $table->string('mfg_no')->nullable()->after('asin');

            // balloon_size replaces size_id.
            $table->char('balloon_size_id', 36)->nullable()->after('material_id');

            // Description.
            $table->text('description')->nullable()->after('name');

            // Packaging.
            $table->char('packaging_id', 36)->nullable()->after('asin');

            // Images (separate single / cluster).
            $table->string('single_image_file_path')->nullable()->after('packaging_id');
            $table->string('cluster_image_file_path')->nullable()->after('single_image_file_path');

            // Computed display name.
            $table->string('computed_name')->nullable()->after('cluster_image_file_path');

            // Price code as FK.
            $table->char('price_code_id', 36)->nullable()->after('computed_name');

            // GS1 prefix (derivable from UPC but stored for lookup perf).
            $table->string('gs1_prefix')->nullable()->after('price_code_id');

            // Status tracking.
            $table->boolean('is_active')->default(true)->after('gs1_prefix');
            $table->timestamp('discontinued_at')->nullable()->after('is_active');

            // Product version.
            $table->string('product_version')->nullable()->after('discontinued_at');

            // is_printed (re-added after the drop above).
            $table->boolean('is_printed')->default(false)->after('product_version');

            // Foreign keys.
            $table->foreign('balloon_size_id')->references('id')->on('balloon_sizes');
            $table->foreign('packaging_id')->references('id')->on('packaging_types');
            $table->foreign('price_code_id')->references('id')->on('price_codes');

            // Indexes.
            $table->index('warehouse_sku');
            $table->unique('upc');
            $table->index('balloon_size_id');
            $table->index('packaging_id');
            $table->index('price_code_id');
            $table->index('computed_name');
            $table->index('is_printed');
            $table->index('is_active');
        });

        // ──────────────────────────────────────────────
        // Step 4: New pivot tables
        // ──────────────────────────────────────────────

        Schema::create('sku_print_colors', function (Blueprint $table) {
            $table->char('sku_id', 36);
            $table->char('print_color_id', 36);

            $table->primary(['sku_id', 'print_color_id']);
            $table->foreign('sku_id')->references('id')->on('skus')->cascadeOnDelete();
            $table->foreign('print_color_id')->references('id')->on('print_colors')->cascadeOnDelete();
        });

        Schema::create('sku_print_sides', function (Blueprint $table) {
            $table->char('sku_id', 36);
            $table->char('print_side_id', 36);

            $table->primary(['sku_id', 'print_side_id']);
            $table->foreign('sku_id')->references('id')->on('skus')->cascadeOnDelete();
            $table->foreign('print_side_id')->references('id')->on('print_sides')->cascadeOnDelete();
        });

        Schema::create('identical_skus', function (Blueprint $table) {
            $table->char('sku_id', 36);
            $table->char('identical_sku_id', 36);

            $table->primary(['sku_id', 'identical_sku_id']);
            $table->foreign('sku_id')->references('id')->on('skus')->cascadeOnDelete();
            $table->foreign('identical_sku_id')->references('id')->on('skus')->cascadeOnDelete();

            // Prevent self-referencing rows and duplicate pairs.
            $table->index('identical_sku_id');
        });

        // ──────────────────────────────────────────────
        // Step 5: Drop upcs table
        // ──────────────────────────────────────────────

        Schema::dropIfExists('upcs');
    }

    public function down(): void
    {
        // Restore upcs table.
        Schema::create('upcs', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('upc_string')->unique();
            $table->char('sku_id', 36);
            $table->char('first_added_by_business_id', 36);
            $table->char('first_added_by_user_id', 36);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('sku_id')->references('id')->on('skus');
            $table->foreign('first_added_by_business_id')->references('id')->on('businesses');
            $table->foreign('first_added_by_user_id')->references('id')->on('users');
            $table->index('sku_id');
        });

        // Drop pivot tables.
        Schema::dropIfExists('identical_skus');
        Schema::dropIfExists('sku_print_sides');
        Schema::dropIfExists('sku_print_colors');

        // Revert skus.
        Schema::table('skus', function (Blueprint $table) {
            $table->dropForeign(['balloon_size_id']);
            $table->dropForeign(['packaging_id']);
            $table->dropForeign(['price_code_id']);

            $table->dropIndex(['warehouse_sku']);
            $table->dropUnique(['upc']);
            $table->dropIndex(['balloon_size_id']);
            $table->dropIndex(['packaging_id']);
            $table->dropIndex(['price_code_id']);
            $table->dropIndex(['computed_name']);
            $table->dropIndex(['is_printed']);
            $table->dropIndex(['is_active']);

            $table->dropColumn([
                'balloon_size_id', 'upc', 'ean', 'asin', 'mfg_no',
                'description', 'packaging_id', 'single_image_file_path',
                'cluster_image_file_path', 'computed_name', 'price_code_id',
                'gs1_prefix', 'is_active', 'discontinued_at', 'product_version',
                'is_printed',
            ]);
        });

        Schema::table('skus', function (Blueprint $table) {
            $table->renameColumn('warehouse_sku', 'manufacturer_sku');

            $table->char('size_id', 36)->nullable()->after('brand_id');
            $table->string('price_code')->nullable()->after('default_count_per_bag');
            $table->string('image_url')->nullable()->after('price_code');
            $table->boolean('is_printed')->default(false)->after('image_url');

            $table->foreign('size_id')->references('id')->on('sizes');
            $table->index('size_id');
            $table->index('price_code');
            $table->index('is_printed');
        });

        // Revert reference tables.
        Schema::table('sizes', function (Blueprint $table) {
            $table->dropColumn(['single_image_file_path', 'cluster_image_file_path']);
        });

        Schema::table('shapes', function (Blueprint $table) {
            $table->dropIndex(['name', 'material_id']);
            $table->unique('name');

            $table->dropForeign(['material_id']);
            $table->dropIndex(['material_id']);
            $table->dropColumn(['material_id', 'image_path']);
        });

        Schema::table('textures', function (Blueprint $table) {
            $table->string('texture_family')->nullable()->after('name');
            $table->index('texture_family');

            $table->dropIndex(['name', 'material_id', 'brand_id']);
            $table->unique('name');

            $table->dropForeign(['material_id']);
            $table->dropForeign(['brand_id']);
            $table->dropForeign(['texture_family_id']);
            $table->dropIndex(['material_id']);
            $table->dropIndex(['brand_id']);
            $table->dropIndex(['texture_family_id']);
            $table->dropColumn(['material_id', 'brand_id', 'texture_family_id', 'image_path']);
        });

        Schema::table('color_families', function (Blueprint $table) {
            $table->renameColumn('fallback_color_hex', 'color_hex');
        });

        Schema::table('color_families', function (Blueprint $table) {
            $table->dropIndex(['name', 'material_id']);
            $table->unique('name');

            $table->dropForeign(['material_id']);
            $table->dropIndex(['material_id']);
            $table->dropColumn(['material_id', 'hex_color_start', 'hex_color_end', 'single_image_file_path', 'cluster_image_file_path']);
        });

        Schema::table('colors', function (Blueprint $table) {
            $table->dropIndex(['name', 'brand_id', 'material_id']);
            $table->unique(['name', 'brand_id', 'deleted_at']);

            $table->dropForeign(['material_id']);
            $table->dropForeign(['texture_id']);
            $table->dropIndex(['material_id']);
            $table->dropIndex(['texture_id']);
            $table->dropColumn(['material_id', 'color_code', 'pms_value', 'texture_id', 'single_image_file_path', 'cluster_image_file_path']);
        });

        Schema::table('materials', function (Blueprint $table) {
            $table->dropColumn(['url', 'image_path']);
        });

        Schema::table('brands', function (Blueprint $table) {
            // Restore the original brand_color_hex (nullable per the 2026_05_11 alter).
            $table->string('brand_color_hex', 7)->nullable()->after('abbreviation');
            $table->dropColumn(['description', 'url_1', 'url_2', 'logo_url', 'primary_color_hex', 'secondary_color_hex', 'is_active']);
        });

        // Drop new lookup tables.
        Schema::dropIfExists('brand_gs1_prefixes');
        Schema::dropIfExists('print_sides');
        Schema::dropIfExists('print_colors');
        Schema::dropIfExists('balloon_sizes');
        Schema::dropIfExists('price_codes');
        Schema::dropIfExists('packaging_types');
        Schema::dropIfExists('texture_families');
    }
};

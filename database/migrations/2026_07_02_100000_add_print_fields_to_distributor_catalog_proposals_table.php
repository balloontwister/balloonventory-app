<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets an admin classify a proposal as a printed product before approving it.
 * The cluster engine defers genuinely-classified "printed" clusters entirely (they
 * never become a proposal), but a mixed-evidence cluster occasionally slips
 * through as solid_latex when one distributor's listing was ambiguous while
 * another correctly flagged it printed — this is the human override for that case.
 *
 * Theme/print-colour/print-side ids are stored as JSON arrays (Sku models them as
 * many-to-many) rather than FK columns, matching this table's FK-less,
 * relocatable-connection convention; the reference rows themselves live on the
 * primary connection like brands/colors/etc.
 */
return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('distributors.connection');
    }

    public function up(): void
    {
        Schema::connection($this->getConnection())->table('distributor_catalog_proposals', function (Blueprint $table) {
            $table->boolean('proposed_is_printed')->default(false)->after('proposed_packaging_id');
            $table->json('proposed_theme_ids')->nullable()->after('proposed_is_printed');
            $table->json('proposed_print_color_ids')->nullable()->after('proposed_theme_ids');
            $table->json('proposed_print_side_ids')->nullable()->after('proposed_print_color_ids');
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->table('distributor_catalog_proposals', function (Blueprint $table) {
            $table->dropColumn(['proposed_is_printed', 'proposed_theme_ids', 'proposed_print_color_ids', 'proposed_print_side_ids']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-managed reference details for a distributor — logo, contact info, and
 * shipping policy. Distinct from the technical `config` JSON; these are shown to
 * users on the Reorder page (e.g. "in stock, free shipping over $X").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('distributors', function (Blueprint $table) {
            $table->string('logo_path')->nullable()->after('description');
            $table->string('contact_email')->nullable()->after('logo_path');
            $table->string('contact_phone', 50)->nullable()->after('contact_email');
            $table->string('contact_url')->nullable()->after('contact_phone');
            $table->decimal('shipping_minimum', 10, 2)->nullable()->after('contact_url');
            $table->decimal('free_shipping_threshold', 10, 2)->nullable()->after('shipping_minimum');
            $table->text('shipping_policy')->nullable()->after('free_shipping_threshold');
            $table->string('hours')->nullable()->after('shipping_policy');
            $table->text('notes')->nullable()->after('hours');
        });
    }

    public function down(): void
    {
        Schema::table('distributors', function (Blueprint $table) {
            $table->dropColumn([
                'logo_path',
                'contact_email',
                'contact_phone',
                'contact_url',
                'shipping_minimum',
                'free_shipping_threshold',
                'shipping_policy',
                'hours',
                'notes',
            ]);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->string('brand_color_hex', 7)->nullable()->change();
            $table->string('logo_path')->nullable()->after('brand_color_hex');
            $table->unsignedSmallInteger('sort_order')->default(0)->after('logo_path');
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->dropIndex(['sort_order']);
            $table->dropColumn(['logo_path', 'sort_order']);
            $table->string('brand_color_hex', 7)->nullable(false)->change();
        });
    }
};

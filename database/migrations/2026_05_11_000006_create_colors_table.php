<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('colors', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('name');
            $table->char('color_family_id', 36);
            $table->char('brand_id', 36)->nullable();
            $table->string('color_hex', 7)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('color_family_id')->references('id')->on('color_families');
            $table->foreign('brand_id')->references('id')->on('brands');

            $table->index('color_family_id');
            $table->index('brand_id');
            $table->index('sort_order');

            // A brand cannot have two active colors with the same name.
            // NULL brand_id rows are unbranded/generic; MariaDB treats NULL != NULL
            // in unique indexes so multiple generic colors with the same name are
            // technically allowed — acceptable for now, enforced at app layer if needed.
            $table->unique(['name', 'brand_id', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('colors');
    }
};

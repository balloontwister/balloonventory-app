<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('color_family_translations', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('color_family_id', 36);
            $table->string('locale', 8);
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->foreign('color_family_id')->references('id')->on('color_families')->cascadeOnDelete();
            $table->unique(['color_family_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('color_family_translations');
    }
};

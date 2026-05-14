<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shape_translations', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('shape_id', 36);
            $table->string('locale', 8);
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->foreign('shape_id')->references('id')->on('shapes')->cascadeOnDelete();
            $table->unique(['shape_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shape_translations');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('texture_translations', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('texture_id', 36);
            $table->string('locale', 8);
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->foreign('texture_id')->references('id')->on('textures')->cascadeOnDelete();
            $table->unique(['texture_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('texture_translations');
    }
};

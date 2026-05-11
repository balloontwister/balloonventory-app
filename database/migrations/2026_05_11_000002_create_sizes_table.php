<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sizes', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('name')->unique();
            $table->enum('size_category', ['small', 'medium', 'large', 'giant', 'small_modeling', 'large_modeling']);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('size_category');
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sizes');
    }
};

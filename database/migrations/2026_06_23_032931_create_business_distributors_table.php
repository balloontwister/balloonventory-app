<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_distributors', function (Blueprint $table) {
            $table->char('business_id', 36);
            $table->char('distributor_id', 36);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->primary(['business_id', 'distributor_id']);
            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('distributor_id')->references('id')->on('distributors')->cascadeOnDelete();

            $table->index('distributor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_distributors');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('distributor_catalog_gaps', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('distributor_id', 36);
            $table->string('external_identifier');
            $table->string('product_name');
            $table->string('product_url', 2048);
            $table->string('reason')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->foreign('distributor_id')->references('id')->on('distributors')->cascadeOnDelete();

            $table->index('distributor_id');
            $table->index('external_identifier');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distributor_catalog_gaps');
    }
};

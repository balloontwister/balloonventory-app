<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('local_prices', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('business_id', 36);
            $table->string('price_code');
            $table->integer('amount_cents');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('business_id')->references('id')->on('businesses');

            $table->index('business_id');
            $table->unique(['business_id', 'price_code', 'deleted_at'], 'lp_business_price_code_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('local_prices');
    }
};

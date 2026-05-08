<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('upcs', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('upc_string')->unique();
            $table->char('sku_id', 36);
            $table->char('first_added_by_business_id', 36);
            $table->char('first_added_by_user_id', 36);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('sku_id')->references('id')->on('skus');
            $table->foreign('first_added_by_business_id')->references('id')->on('businesses');
            $table->foreign('first_added_by_user_id')->references('id')->on('users');

            $table->index('sku_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('upcs');
    }
};

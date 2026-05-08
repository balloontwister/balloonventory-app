<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Table is `lists`; model is BalloonList (PHP reserves `list` as a language construct).
        Schema::create('lists', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('business_id', 36);
            $table->string('name');
            $table->boolean('is_business_favorites')->default(false);
            $table->text('notes')->nullable();
            $table->char('created_by_user_id', 36);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('business_id')->references('id')->on('businesses');
            $table->foreign('created_by_user_id')->references('id')->on('users');

            $table->index('business_id');
            $table->index('is_business_favorites');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lists');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jobs', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('business_id', 36);
            $table->string('name');
            $table->string('client_name')->nullable();
            $table->date('event_date')->nullable();
            $table->enum('status', ['draft', 'planned', 'in_progress', 'archived'])->default('draft');
            $table->text('notes')->nullable();
            $table->char('created_by_user_id', 36);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('business_id')->references('id')->on('businesses');
            $table->foreign('created_by_user_id')->references('id')->on('users');

            $table->index('business_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobs');
    }
};

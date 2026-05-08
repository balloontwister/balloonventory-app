<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memberships', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('user_id', 36);
            $table->char('business_id', 36);
            $table->enum('role', ['owner', 'manager', 'staff', 'guest']);
            $table->string('business_badge_color', 7)->default('#6366F1');
            $table->timestamp('joined_at');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('business_id')->references('id')->on('businesses');

            $table->index('user_id');
            $table->index('business_id');

            // MariaDB NULL != NULL in unique indexes: one active row per (user, business),
            // multiple soft-deleted rows coexist without conflict.
            $table->unique(['user_id', 'business_id', 'deleted_at'], 'memberships_user_business_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};

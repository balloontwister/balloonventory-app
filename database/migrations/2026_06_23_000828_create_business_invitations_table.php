<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('business_invitations', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('business_id', 36);
            $table->string('invited_email');
            $table->char('invited_user_id', 36);
            $table->string('role');
            $table->string('token', 64)->unique();
            $table->char('invited_by_user_id', 36);
            $table->string('status')->default('pending');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('business_id')->references('id')->on('businesses');
            $table->foreign('invited_user_id')->references('id')->on('users');
            $table->foreign('invited_by_user_id')->references('id')->on('users');

            $table->index('business_id');
            $table->index('invited_email');
            $table->index('invited_user_id');

            // App-layer uniqueness per DATA.md convention: allows re-invitation after
            // a soft-deleted invite, while preventing two active invites for the same user+business.
            $table->unique(['business_id', 'invited_user_id', 'deleted_at'], 'bi_business_user_deleted_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_invitations');
    }
};

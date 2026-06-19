<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin replies to a user's "feedback on this item" report — the message emailed
 * back to close the loop once the report has been read and acted on. Mirrors
 * support_ticket_replies.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sku_feedback_replies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('sku_feedback_id', 36)->index();
            $table->char('user_id', 36)->nullable(); // admin who replied
            $table->text('body');
            $table->timestamps();

            $table->foreign('sku_feedback_id')
                ->references('id')
                ->on('sku_feedback')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sku_feedback_replies');
    }
};

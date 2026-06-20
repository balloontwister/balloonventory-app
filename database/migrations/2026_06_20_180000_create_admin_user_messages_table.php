<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A one-off email an admin composed and sent to a specific user from the admin
 * tools. The send itself is also recorded in email_logs (via LogSentEmail); this
 * table additionally keeps the full body so the message is reviewable later on
 * the user detail page. Mirrors sku_feedback_replies.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_user_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('user_id', 36)->index();        // recipient
            $table->char('sender_user_id', 36)->nullable(); // admin who sent it
            $table->string('subject');
            $table->text('body');
            $table->string('template_key')->nullable();  // template that seeded the draft, if any
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_user_messages');
    }
};

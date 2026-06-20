<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only login history: one row per sign-in attempt outcome (success,
 * failed, lockout). Not tenant-scoped — a platform audit trail reviewed by
 * admins on the user detail page. user_id is null for failed/lockout attempts
 * where the email doesn't match an account; the attempted email is kept for
 * context. Pruned after 18 months by app:prune-login-events.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_events', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('user_id', 36)->nullable()->index();
            $table->string('email')->nullable()->index();
            $table->string('event', 16); // success | failed | lockout
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_events');
    }
};

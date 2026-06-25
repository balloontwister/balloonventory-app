<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Single-use, short-lived passwordless login links an admin generates for a user
 * from the admin tools (e.g. to email a customer who's locked out, or to open the
 * account themselves for support). Only the SHA-256 hash of the token is stored;
 * the raw token lives in the URL and is never persisted. App-enforced references
 * (no DB foreign keys), matching the rest of the admin audit-style tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('magic_login_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('user_id', 36)->index();               // who the link logs in
            $table->char('created_by_user_id', 36)->nullable(); // admin who generated it
            $table->string('token_hash', 64)->unique();         // sha256 hex of the raw token
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('magic_login_links');
    }
};

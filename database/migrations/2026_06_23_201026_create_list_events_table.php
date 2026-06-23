<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('list_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->constrained('businesses');
            // No cascade delete — preserve audit history even after a list is deleted.
            $table->foreignUuid('list_id')->constrained('lists');
            $table->foreignUuid('user_id')->nullable()->constrained('users');
            $table->string('event_type');
            $table->json('payload')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('list_events');
    }
};

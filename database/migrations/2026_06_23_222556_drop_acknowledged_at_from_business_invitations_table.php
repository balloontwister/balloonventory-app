<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The post-join acknowledgement notice was replaced by the unified
     * notifications system, leaving this column unused.
     */
    public function up(): void
    {
        Schema::table('business_invitations', function (Blueprint $table) {
            $table->dropColumn('acknowledged_at');
        });
    }

    public function down(): void
    {
        Schema::table('business_invitations', function (Blueprint $table) {
            $table->timestamp('acknowledged_at')->nullable();
        });
    }
};

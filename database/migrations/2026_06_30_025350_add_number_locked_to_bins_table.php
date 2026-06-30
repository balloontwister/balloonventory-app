<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bins', function (Blueprint $table) {
            // When true, the bin's number is pinned: auto-numbering (fill or full
            // renumber) never reassigns it. Lets a user protect a bin whose
            // physical label is already printed.
            $table->boolean('number_locked')->default(false)->after('number');
        });
    }

    public function down(): void
    {
        Schema::table('bins', function (Blueprint $table) {
            $table->dropColumn('number_locked');
        });
    }
};

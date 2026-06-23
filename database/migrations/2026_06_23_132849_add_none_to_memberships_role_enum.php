<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->enum('role', ['owner', 'manager', 'staff', 'guest', 'none'])->change();
        });
    }

    public function down(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->enum('role', ['owner', 'manager', 'staff', 'guest'])->change();
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 32)->nullable()->after('timezone');
            $table->string('address_line1')->nullable()->after('phone');
            $table->string('address_line2')->nullable()->after('address_line1');
            $table->string('city')->nullable()->after('address_line2');
            $table->string('state_region')->nullable()->after('city');
            $table->string('postal_code', 20)->nullable()->after('state_region');
            $table->char('country', 2)->nullable()->after('postal_code');
            $table->string('website_url')->nullable()->after('country');
            $table->string('website_url_2')->nullable()->after('website_url');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone', 'address_line1', 'address_line2', 'city',
                'state_region', 'postal_code', 'country', 'website_url', 'website_url_2',
            ]);
        });
    }
};

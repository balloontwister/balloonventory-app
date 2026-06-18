<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('business_type')->nullable()->after('plan');
            $table->json('onboarding_answers')->nullable()->after('business_type');
            $table->timestamp('onboarding_completed_at')->nullable()->after('onboarding_answers');
        });

        Schema::table('stock_levels', function (Blueprint $table) {
            $table->boolean('is_sample')->default(false)->after('open_bags');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->boolean('is_sample')->default(false)->after('open_bags_change');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['business_type', 'onboarding_answers', 'onboarding_completed_at']);
        });

        Schema::table('stock_levels', function (Blueprint $table) {
            $table->dropColumn('is_sample');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropColumn('is_sample');
        });
    }
};

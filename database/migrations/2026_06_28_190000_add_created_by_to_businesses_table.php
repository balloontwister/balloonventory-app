<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->foreignUuid('created_by_user_id')->nullable()->after('slug')
                ->constrained('users')->nullOnDelete();
        });

        // Backfill: the creator is recorded on the auto-seeded Favorites list
        // (BalloonList.created_by_user_id), which BusinessController@store sets to
        // the creating user. Guarded by hasTable so a fresh-DB migration run
        // (where these tables come later) is a safe no-op.
        if (Schema::hasTable('balloon_lists')) {
            DB::statement('
                update businesses set created_by_user_id = (
                    select bl.created_by_user_id from balloon_lists bl
                    where bl.business_id = businesses.id and bl.is_business_favorites = 1
                    order by bl.created_at asc limit 1
                )
            ');
        }

        // Fallback for any business without that signal: earliest owner.
        if (Schema::hasTable('memberships')) {
            DB::statement("
                update businesses set created_by_user_id = (
                    select m.user_id from memberships m
                    where m.business_id = businesses.id and m.role = 'owner' and m.deleted_at is null
                    order by m.joined_at asc limit 1
                ) where created_by_user_id is null
            ");
        }
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropForeign(['created_by_user_id']);
            $table->dropColumn('created_by_user_id');
        });
    }
};

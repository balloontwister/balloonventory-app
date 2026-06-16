<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            // Pairs the two legs of a bin-to-bin transfer (source debit + dest
            // credit) so they can be displayed and undone together. Null for
            // every non-transfer movement.
            $table->char('transfer_id', 36)->nullable()->after('bin_id');
            $table->index('transfer_id');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex(['transfer_id']);
            $table->dropColumn('transfer_id');
        });
    }
};

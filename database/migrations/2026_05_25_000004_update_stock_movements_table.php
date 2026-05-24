<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            // Replace single decimal change column with per-type bag counters.
            $table->dropColumn('quantity_change');
            $table->integer('full_bags_change')->default(0)->after('direction');
            $table->integer('open_bags_change')->default(0)->after('full_bags_change');

            // Extend direction to cover lifecycle events beyond check-in/out.
            $table->enum('direction', ['in', 'out', 'removed', 'restored', 'adjusted'])
                ->default('in')
                ->change();

            // Which bin was affected; nullable so pre-bin-era rows remain valid.
            $table->char('bin_id', 36)->nullable()->after('job_id');
            $table->foreign('bin_id')->references('id')->on('bins');
            $table->index('bin_id');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign(['bin_id']);
            $table->dropIndex(['bin_id']);
            $table->dropColumn(['full_bags_change', 'open_bags_change', 'bin_id']);
            $table->decimal('quantity_change', 10, 2)->after('direction');
            $table->enum('direction', ['in', 'out'])->default('in')->change();
        });
    }
};

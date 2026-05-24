<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bins', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('business_id', 36);
            $table->char('location_id', 36);
            $table->smallInteger('number')->unsigned()->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            // Internal scan code format: "BIN-{token}", unique platform-wide.
            // Reserved for Scan-page bin-view integration (deferred sprint).
            $table->string('scan_code')->nullable()->unique();
            $table->boolean('is_default')->default(false)->index();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('business_id')->references('id')->on('businesses');
            $table->foreign('location_id')->references('id')->on('locations');

            $table->index('business_id');
            $table->index('location_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bins');
    }
};

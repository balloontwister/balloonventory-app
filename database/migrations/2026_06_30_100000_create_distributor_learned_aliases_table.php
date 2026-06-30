<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A learned mapping from a raw distributor attribute value to a catalog
 * reference row, captured the moment an admin corrects a proposal in the review
 * queue. The matcher consults these (between the curated-alias/exact tier and the
 * fuzzy fallback) so a correction made once — "Red Fashion" → "Fashion Red",
 * "Yellow / Gold" → the right shade — sticks for every future listing without a
 * developer adding a config line.
 *
 * Scope is per-distributor + brand (the safest generalization): `brand_id` is the
 * brand the size/colour belongs to, and the empty string for the brand and
 * packaging attributes which carry no brand scope. The empty-string sentinel
 * (rather than NULL) keeps the unique index reliable on both MySQL and SQLite,
 * where NULLs are treated as distinct.
 *
 * FK-less like the rest of the proposal data: `distributor_id`, `brand_id` and
 * `catalog_id` point at rows that may live on a different connection, so they're
 * stitched in PHP, never joined. Lives on the relocatable `distributors`
 * connection.
 */
return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('distributors.connection');
    }

    public function up(): void
    {
        Schema::connection($this->getConnection())->create('distributor_learned_aliases', function (Blueprint $table) {
            $table->char('id', 36)->primary();

            $table->char('distributor_id', 36);
            $table->string('attribute', 16); // brand|size|color|packaging

            // Brand scope for the brand-scoped attributes (size, colour). Empty
            // string for brand/packaging, which resolve without a brand.
            $table->char('brand_id', 36)->default('');

            // The distributor's raw value, lower-cased + trimmed — the key the
            // matcher looks up.
            $table->string('raw_value_normalized');

            // The catalog reference row the admin chose.
            $table->char('catalog_id', 36);

            // Free-text reasoning banked for the Phase 2 LLM matcher.
            $table->text('note')->nullable();

            $table->char('created_by', 36)->nullable();

            $table->timestamps();

            $table->unique(
                ['distributor_id', 'attribute', 'brand_id', 'raw_value_normalized'],
                'distributor_learned_aliases_scope_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('distributor_learned_aliases');
    }
};

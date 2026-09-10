<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book B FIN-02 §2. WHAT is charged — one row per component a matched
 * structure bills. `tier_bands`/`subject_rate_map` are the two knobs
 * the whole full-time/part-time distinction is built from (§4) — there
 * is no code branch for either billing mode.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_structure_items', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('structure_id')->constrained('fee_structures')->cascadeOnDelete();
            $table->foreignId('component_id')->constrained('fee_components');
            $table->string('billing_basis', 20);
            $table->bigInteger('amount_minor')->nullable();
            $table->char('currency', 3);
            $table->bigInteger('unit_rate_minor')->nullable();
            $table->string('unit_label', 40)->nullable();
            $table->bigInteger('minimum_minor')->nullable();
            $table->bigInteger('maximum_minor')->nullable();
            $table->json('tier_bands')->nullable();
            $table->json('subject_rate_map')->nullable();
            $table->boolean('is_prorated')->default(true);
            $table->string('proration_basis', 20)->default('day');
            $table->string('charge_frequency', 20)->default('termly');
            $table->boolean('is_optional')->default(false);
            $table->smallInteger('sort_order')->nullable();

            $table->index('structure_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_structure_items');
    }
};

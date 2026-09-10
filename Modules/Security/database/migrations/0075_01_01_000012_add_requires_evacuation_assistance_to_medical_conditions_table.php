<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H2 OPS-06 §3/BR-OPS-06-009. A real gap, not a spec column:
 * nothing in `Modules\Welfare`'s own `medical_conditions` (Book G
 * BRD-06) or `Modules\People`'s `students` (Book C) carries a
 * discrete mobility/evacuation-assistance flag — the closest existing
 * field, `medical_conditions.accommodation_requirement`, is free text
 * matched by substring for bed allocation (BRD-01) only, unsafe to
 * reuse for a safety-critical muster flag. This is an additive,
 * nullable-default-false column on an already-shipped Book G table —
 * safe, reversible, and doesn't touch `Modules\Welfare`'s own
 * migration files.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medical_conditions', function (Blueprint $table): void {
            $table->boolean('requires_evacuation_assistance')->default(false)->after('affects_accommodation');
        });
    }

    public function down(): void
    {
        Schema::table('medical_conditions', function (Blueprint $table): void {
            $table->dropColumn('requires_evacuation_assistance');
        });
    }
};

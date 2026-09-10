<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book J INT-03 §2/BR-INT-03-006. Keyed by guardian, not student — a
 * fee default risk is a household-level signal. `recommended_action`
 * is advisory text only; `FIN-03`'s own reminder ladder is untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_default_risk_scores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained();
            $table->foreignId('guardian_id')->constrained();
            $table->decimal('risk_score', 5, 2);
            $table->json('contributing_factors');
            $table->string('recommended_action', 60)->nullable();
            $table->timestamp('computed_at');

            $table->unique(['school_id', 'guardian_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_default_risk_scores');
    }
};

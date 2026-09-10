<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book J INT-03 §2/§3 ⭐/BR-INT-03-001/002/004/005. A rebuilt-nightly
 * cache, one row per (school, student, term) — history across terms
 * is retained by never reusing a prior term's row. `contributing_factors`
 * is the explanation itself (BR-INT-03-001), never a bare score. This
 * table doubles as the "At-risk review queue" screen's own backing
 * data (banded, sortable) — AC-INT-03-003's "review queue entry" is a
 * row here reaching `risk_band = 'critical'`, not a second table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learner_risk_scores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained();
            $table->foreignId('term_id')->constrained();
            $table->decimal('composite_score', 5, 2);
            $table->string('risk_band', 20);
            $table->json('contributing_factors');
            $table->timestamp('computed_at');

            $table->unique(['school_id', 'student_id', 'term_id']);
            $table->index(['school_id', 'term_id', 'risk_band']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learner_risk_scores');
    }
};

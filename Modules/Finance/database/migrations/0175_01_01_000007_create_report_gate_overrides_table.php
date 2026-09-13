<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book B FIN-03 §2/BR-FIN-03-018. Not in the spec's own §2 data model
 * (the report-gate CHECK itself needs no storage — it just compares a
 * live balance against a threshold — but "per-learner override is
 * permitted with a reason and is logged" does). One row per
 * (school, student, term): a fresh override is required every term,
 * never silently carried forward — a school clearing a hardship case
 * for one term should not accidentally clear every future term too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_gate_overrides', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained();
            $table->foreignId('term_id')->constrained();
            $table->text('reason');
            $table->foreignId('granted_by')->constrained('users');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['school_id', 'student_id', 'term_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_gate_overrides');
    }
};

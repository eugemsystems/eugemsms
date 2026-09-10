<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book D ACA-02 §2/BR-ACA-02-015/016. The option-choice workflow for
 * Form 3/Lower 6 entry. `indicative_fee_minor` is computed by `FIN-02`'s
 * `PreviewIndicativeFeeAction` at submission time and stored here as a
 * snapshot — this table never calls into Finance itself, preserving the
 * one-way Finance→Academic module dependency the rest of the codebase
 * keeps; the caller resolves the preview first and passes the figure
 * in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subject_selection_submissions', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->foreignId('student_id')->constrained();
            $table->foreignId('grade_level_id')->constrained();
            $table->foreignId('pathway_id')->nullable()->constrained('pathways')->nullOnDelete();
            $table->json('selected_subject_ids');
            $table->json('reserve_subject_ids')->nullable();
            $table->json('validation_result')->nullable();
            $table->bigInteger('indicative_fee_minor')->nullable();
            $table->char('indicative_fee_currency', 3)->nullable();
            $table->string('status', 20);
            $table->foreignId('submitted_by')->nullable()->constrained('users');
            $table->foreignId('guardian_approved_by')->nullable()->constrained('users');
            $table->foreignId('school_approved_by')->nullable()->constrained('users');
            $table->string('rejection_reason', 255)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('allocated_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'academic_year_id', 'student_id'], 'subject_selection_submissions_student_idx');
            $table->index(['school_id', 'status'], 'subject_selection_submissions_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_selection_submissions');
    }
};

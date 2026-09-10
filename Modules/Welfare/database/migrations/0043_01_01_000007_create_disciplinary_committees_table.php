<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book G BRD-07 §2/BR-BRD-07-006 — suspension/exclusion requires this
 * to contain the learner's own statement, or an explicit note that
 * they declined to give one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disciplinary_committees', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained();
            $table->date('convened_on');
            $table->json('panel_staff_ids');
            $table->boolean('guardian_present')->nullable();
            $table->boolean('learner_present')->nullable();
            $table->text('learner_statement')->nullable();
            $table->text('guardian_statement')->nullable();
            $table->json('evidence_reviewed')->nullable();
            $table->text('findings');
            $table->string('decision', 40);
            $table->foreignId('recommended_sanction_id')->nullable()->constrained('sanctions');
            $table->unsignedBigInteger('minutes_document_id')->nullable();
            $table->foreignId('chaired_by')->constrained('users');

            $table->index(['school_id', 'student_id', 'convened_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disciplinary_committees');
    }
};

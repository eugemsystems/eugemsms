<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book E ACA-06 §6/§7/BR-ACA-06-018 (`Academic\Projects\Export`,
 * `projects.export`). One row per export run — the document itself
 * (rendered through a school's own configurable `DocumentTemplate`) is
 * the source of truth; this is the audit trail of when it was run, by
 * whom, against which instrument/year, and how many candidates it
 * covered, same shape `project_portfolios`/`board_packs` already use.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_national_submissions', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instrument_id')->constrained('assessment_instruments');
            $table->foreignId('academic_year_id')->constrained();
            $table->foreignId('document_id')->nullable()->constrained('documents');
            $table->unsignedInteger('candidate_count');
            $table->foreignId('exported_by')->constrained('users');
            $table->timestamp('exported_at');

            $table->index(['school_id', 'instrument_id', 'academic_year_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_national_submissions');
    }
};

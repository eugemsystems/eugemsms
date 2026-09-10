<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book E ACA-06 §2/BR-ACA-06-010. `file_id` has no FK constraint —
 * `CORE-10`'s virus-scanned file store; same forward-reference
 * pattern used throughout this codebase for a not-yet-built module's
 * table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_evidence', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('learner_project_id')->constrained('learner_projects')->cascadeOnDelete();
            $table->foreignId('milestone_id')->nullable()->constrained('project_milestones');
            $table->string('evidence_type', 30);
            $table->unsignedBigInteger('file_id')->nullable();
            $table->string('external_url', 500)->nullable();
            $table->string('caption', 255)->nullable();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamp('uploaded_at');
            $table->boolean('is_final_submission')->default(false);

            $table->index(['school_id', 'learner_project_id'], 'project_evidence_project_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_evidence');
    }
};

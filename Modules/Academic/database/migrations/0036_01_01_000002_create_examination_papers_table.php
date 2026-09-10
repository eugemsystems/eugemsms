<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book E ACA-07 §2/§3 ⭐. `paper_file_id`/`marking_scheme_file_id` are
 * forward references to `CORE-10` (no FK yet, matching the
 * `ProjectBrief.brief_document_id` precedent) — per-session file
 * encryption and watermarking are `CORE-10`-level infrastructure not
 * built in this pass; see `ReleaseExaminationPaperAction`'s own
 * docblock for exactly which controls this migration's columns
 * support today (`release_at` time-lock, setter/vetter separation,
 * one-way seal) versus which remain deferred.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('examination_papers', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('session_id')->constrained('examination_sessions');
            $table->foreignId('subject_id')->constrained();
            $table->foreignId('grade_level_id')->constrained();
            $table->string('paper_number', 10);
            $table->string('paper_name', 120);
            $table->string('component_type', 20);
            $table->decimal('max_mark', 6, 2);
            $table->decimal('weight_percent', 5, 2);
            $table->smallInteger('duration_minutes');
            $table->date('scheduled_date')->nullable();
            $table->time('scheduled_start')->nullable();
            $table->string('requires_special_venue', 30)->nullable();
            $table->unsignedBigInteger('paper_file_id')->nullable();
            $table->unsignedBigInteger('marking_scheme_file_id')->nullable();
            $table->timestamp('release_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users');
            $table->foreignId('setter_staff_id')->nullable()->constrained('staff');
            $table->foreignId('vetted_by')->nullable()->constrained('staff');
            $table->timestamp('vetted_at')->nullable();
            $table->string('status', 20);
            $table->timestamps();

            $table->unique(['school_id', 'session_id', 'subject_id', 'grade_level_id', 'paper_number'], 'exam_papers_unique');
            $table->index(['school_id', 'session_id', 'scheduled_date'], 'exam_papers_schedule_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('examination_papers');
    }
};

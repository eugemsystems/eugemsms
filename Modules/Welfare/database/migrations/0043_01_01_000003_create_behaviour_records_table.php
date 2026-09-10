<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book G BRD-07 §2/§3 ⭐/BR-BRD-07-001/016/017/018. `safeguarding_case_id`
 * is a forward reference to `BRD-08` (built after this module within
 * the same book) — plain nullable column, no FK, matching the
 * established forward-reference pattern. `hostel_id` similarly stays
 * an uncostrained reference into `Boarding` rather than a hard FK,
 * since this table only ever uses it for reporting/filtering, never
 * referential integrity a hostel deletion must cascade through.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('behaviour_records', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->foreignId('term_id')->constrained();
            $table->foreignId('student_id')->constrained();
            $table->foreignId('category_id')->constrained('behaviour_categories');
            $table->string('polarity', 10);
            $table->smallInteger('points');
            $table->timestamp('occurred_at');
            $table->string('location', 120)->nullable();
            $table->string('context', 40)->nullable();
            $table->foreignId('subject_id')->nullable()->constrained('subjects');
            $table->unsignedBigInteger('class_id')->nullable();
            $table->unsignedBigInteger('hostel_id')->nullable();
            $table->text('description');
            $table->json('witnesses')->nullable();
            $table->json('other_learners_involved')->nullable();
            $table->json('evidence_file_ids')->nullable();
            $table->foreignId('reported_by')->constrained('users');
            $table->string('status', 20);
            $table->timestamp('guardian_notified_at')->nullable();
            $table->boolean('is_confidential')->default(false);
            $table->unsignedBigInteger('safeguarding_case_id')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'student_id', 'occurred_at']);
            $table->index(['school_id', 'term_id', 'polarity']);
            $table->index(['school_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('behaviour_records');
    }
};

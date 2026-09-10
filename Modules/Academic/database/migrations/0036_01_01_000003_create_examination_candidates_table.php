<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book E ACA-07 §2/BR-ACA-07-001/002. `index_number` is allocated
 * gapless per session/level by `IndexNumberAllocator`, never typed by
 * hand. `entered_subjects` is derived from `ACA-02` enrolments at
 * confirmation time, then frozen — it is a snapshot, not a live join.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('examination_candidates', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('session_id')->constrained('examination_sessions');
            $table->foreignId('student_id')->constrained();
            $table->string('index_number', 40);
            $table->string('entry_status', 20);
            $table->json('entered_subjects');
            $table->bigInteger('entry_fee_minor')->nullable();
            $table->char('entry_fee_currency', 3)->nullable();
            $table->boolean('entry_invoiced')->default(false);
            $table->unsignedBigInteger('statement_of_entry_doc_id')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'session_id', 'student_id'], 'exam_candidates_student_unique');
            $table->unique(['school_id', 'session_id', 'index_number'], 'exam_candidates_index_unique');
            $table->index(['school_id', 'session_id', 'entry_status'], 'exam_candidates_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('examination_candidates');
    }
};

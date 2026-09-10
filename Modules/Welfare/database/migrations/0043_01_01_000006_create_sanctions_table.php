<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book G BRD-07 §2/BR-BRD-07-004/005/009/010 ⭐. `committee_record_id`
 * has no FK constraint — `disciplinary_committees` links back here via
 * `recommended_sanction_id`, and constraining both directions would
 * create a migration-order cycle (the same shape already used for
 * `health_incidents`/`external_referrals` in this module).
 * `approval_request_id` (`CORE-07`) and `document_id` (`CORE-10`) are
 * forward references, not built.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sanctions', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->foreignId('term_id')->constrained();
            $table->foreignId('student_id')->constrained();
            $table->foreignId('sanction_type_id')->constrained('sanction_types');
            $table->json('behaviour_record_ids');
            $table->text('reason');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->smallInteger('duration_days')->nullable();
            $table->string('status', 20);
            $table->unsignedBigInteger('approval_request_id')->nullable();
            $table->foreignId('issued_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('guardian_notified_at')->nullable();
            $table->timestamp('guardian_meeting_at')->nullable();
            $table->text('guardian_meeting_notes')->nullable();
            $table->unsignedBigInteger('committee_record_id')->nullable();
            $table->text('conditions')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('completion_notes')->nullable();
            $table->unsignedBigInteger('document_id')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'student_id', 'status']);
            $table->index(['school_id', 'status', 'starts_on', 'ends_on'], 'sanctions_status_dates_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanctions');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book E ACA-07 §2/BR-ACA-07-018/019. Confidential by default
 * (`is_confidential`) — field/row-level tiered visibility is enforced
 * at the Livewire/API layer, not built in this pass (no screens yet);
 * this table and its Actions are the enforceable structural half.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('malpractice_incidents', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('session_id')->constrained('examination_sessions');
            $table->foreignId('paper_id')->nullable()->constrained('examination_papers');
            $table->foreignId('candidate_id')->nullable()->constrained('examination_candidates');
            $table->string('incident_type', 40);
            $table->text('description');
            $table->json('evidence_file_ids')->nullable();
            $table->foreignId('reported_by')->constrained('users');
            $table->timestamp('occurred_at');
            $table->text('investigation_notes')->nullable();
            $table->string('outcome', 40)->nullable();
            $table->foreignId('outcome_by')->nullable()->constrained('users');
            $table->string('status', 20);
            $table->boolean('is_confidential')->default(true);

            $table->index(['school_id', 'session_id'], 'malpractice_session_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('malpractice_incidents');
    }
};

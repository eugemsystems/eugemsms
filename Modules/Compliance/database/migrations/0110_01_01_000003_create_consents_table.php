<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H3 CMP-03 §2/BR-CMP-03-001/002 ⭐ — APPEND-ONLY. Withdrawal is a
 * new state on the SAME record (`withdrawn_at` etc set on the row that
 * was granted), never a new row and never a deletion — matching the
 * literal spec wording, enforced by the model's own guard rather than
 * a DB grant revocation in this pass (see the model's own docblock for
 * why: this table doesn't yet have the dedicated DB-user grant
 * infrastructure Finance/safeguarding's append-only tables were given
 * in Book A/B).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consents', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('consent_type_id')->constrained('consent_types');
            $table->string('subject_type', 20);
            $table->unsignedBigInteger('subject_id');
            $table->string('granted_by_type', 20);
            $table->unsignedBigInteger('granted_by_id');
            $table->boolean('granted');
            $table->timestamp('granted_at');
            $table->string('method', 30);
            $table->string('notice_version', 20);
            $table->unsignedBigInteger('document_file_id')->nullable();
            $table->foreignId('witness_staff_id')->nullable()->constrained('staff');
            $table->string('ip_address', 45)->nullable();
            $table->date('expires_on')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->foreignId('withdrawn_by')->nullable()->constrained('users');
            $table->string('withdrawal_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['school_id', 'subject_type', 'subject_id', 'consent_type_id'], 'consents_subject_idx');
            $table->index(['school_id', 'expires_on'], 'consents_expires_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consents');
    }
};

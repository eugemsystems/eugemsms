<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book G BRD-08 §2/§3 ⭐⭐/BR-BRD-08-005/006 — SEPARATE HARDENED STREAM
 * from `CORE-08`'s general `data_access_log`/`financial_audit_log`.
 * Hash-chained the same way `financial_audit_log` is (see
 * `Modules\Core\Models\FinancialAuditLogEntry` for the shared
 * reasoning on why the real DB-grant REVOKE is a deployment step, not
 * a migration).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('safeguarding_audit', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('sequence');
            $table->string('event_type', 40);
            $table->unsignedBigInteger('case_id')->nullable();
            $table->unsignedBigInteger('concern_id')->nullable();
            $table->foreignId('user_id')->constrained('users');
            $table->string('user_role_at_time', 80);
            $table->string('access_basis', 80)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->char('payload_hash', 64);
            $table->char('previous_hash', 64)->nullable();
            $table->timestamp('occurred_at', 6);

            $table->unique(['school_id', 'sequence']);
            $table->index(['school_id', 'case_id', 'occurred_at']);
            $table->index(['school_id', 'event_type', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('safeguarding_audit');
    }
};

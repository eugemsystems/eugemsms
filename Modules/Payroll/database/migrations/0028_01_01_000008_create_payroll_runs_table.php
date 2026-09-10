<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H3 PPL-05 §2/§4 ⭐ — the run's own state machine (draft →
 * computing → preview → approved → posted → paid, or reversed /
 * cancelled) is BR-PPL-05-013's "four distinct states": compute,
 * preview, approve, post are never collapsed into one write.
 * `approval_request_id` (CORE-07) is left unconstrained, matching
 * this codebase's established pattern for approval-chain columns
 * ahead of that wiring (see `leave_requests.approval_request_id`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_runs', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->foreignId('term_id')->constrained();
            $table->char('period_month', 7);
            $table->string('run_number', 40);
            $table->string('run_type', 20);
            $table->date('pay_date');
            $table->date('period_start');
            $table->date('period_end');
            $table->smallInteger('staff_count')->default(0);
            $table->bigInteger('gross_minor')->default(0);
            $table->bigInteger('deductions_minor')->default(0);
            $table->bigInteger('net_minor')->default(0);
            $table->bigInteger('employer_cost_minor')->default(0);
            $table->json('currency_totals')->nullable();
            $table->bigInteger('paye_minor')->default(0);
            $table->bigInteger('aids_levy_minor')->default(0);
            $table->bigInteger('nssa_employee_minor')->default(0);
            $table->bigInteger('nssa_employer_minor')->default(0);
            $table->bigInteger('apwcs_minor')->default(0);
            $table->bigInteger('zimdef_minor')->default(0);
            $table->bigInteger('nec_employee_minor')->default(0);
            $table->bigInteger('nec_employer_minor')->default(0);
            $table->string('status', 20);
            $table->json('variance_report')->nullable();
            $table->json('exception_report')->nullable();
            $table->unsignedBigInteger('approval_request_id')->nullable();
            $table->foreignId('computed_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('journal_id')->nullable()->constrained('journals')->nullOnDelete();
            $table->foreignId('bank_file_id')->nullable()->constrained('files')->nullOnDelete();

            $table->unique(['school_id', 'period_month', 'run_type', 'run_number']);
            $table->index(['school_id', 'status', 'pay_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_runs');
    }
};

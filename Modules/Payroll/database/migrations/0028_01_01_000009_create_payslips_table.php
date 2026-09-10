<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H3 PPL-05 §2/§3 ⭐ — `calculation_trace` is the full derivation
 * (bands applied, NSSA ceiling and whether it bit, the AIDS Levy
 * base) that BR-PPL-05-017/AC-PPL-05-004 require to be visible on
 * demand. Every monetary column here is plain `BIGINT` — the spec's
 * own SQL for this table carries no `ENCRYPTED` marker (unlike
 * `staff_contracts.basic_salary_minor`); "payslips are encrypted"
 * (BR-PPL-05-017) is an access-control/at-rest-storage concern for
 * the generated payslip *document*, not a column-level requirement
 * here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payslips', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->string('payslip_number', 40);

            $table->bigInteger('basic_minor');
            $table->bigInteger('allowances_minor')->default(0);
            $table->bigInteger('overtime_minor')->default(0);
            $table->bigInteger('bonus_minor')->default(0);
            $table->bigInteger('gross_minor');
            $table->bigInteger('taxable_gross_minor');
            $table->bigInteger('pensionable_gross_minor');

            $table->bigInteger('paye_minor')->default(0);
            $table->bigInteger('aids_levy_minor')->default(0);
            $table->bigInteger('nssa_employee_minor')->default(0);
            $table->bigInteger('nec_employee_minor')->default(0);

            $table->bigInteger('loan_deduction_minor')->default(0);
            $table->bigInteger('third_party_minor')->default(0);
            $table->bigInteger('fee_offset_minor')->default(0);
            $table->bigInteger('other_deductions_minor')->default(0);
            $table->bigInteger('total_deductions_minor');
            $table->bigInteger('net_pay_minor');

            $table->bigInteger('nssa_employer_minor')->default(0);
            $table->bigInteger('apwcs_minor')->default(0);
            $table->bigInteger('zimdef_minor')->default(0);
            $table->bigInteger('nec_employer_minor')->default(0);
            $table->bigInteger('employer_cost_minor');
            $table->char('currency', 3);

            $table->bigInteger('usd_net_minor')->nullable();
            $table->bigInteger('zwg_net_minor')->nullable();
            $table->foreignId('exchange_rate_id')->nullable()->constrained('exchange_rates')->nullOnDelete();

            $table->bigInteger('ytd_gross_minor');
            $table->bigInteger('ytd_paye_minor');
            $table->bigInteger('ytd_nssa_minor');
            $table->decimal('days_worked', 5, 2)->nullable();
            $table->decimal('unpaid_leave_days', 5, 2)->default(0);
            $table->json('calculation_trace')->nullable();
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->timestamp('distributed_at')->nullable();

            $table->unique(['payroll_run_id', 'staff_id']);
            $table->index(['school_id', 'staff_id', 'payroll_run_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslips');
    }
};

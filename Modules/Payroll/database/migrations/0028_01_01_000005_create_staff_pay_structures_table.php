<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H3 PPL-05 §2 ⭐ — per staff member, dated. A staff member has
 * at most one `active` structure at a time, mirroring PPL-04's
 * one-active-contract rule for the same reason: a new structure
 * supersedes rather than overwrites, so a past payslip's pay
 * structure is never rewritten by a later raise.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_pay_structures', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained('staff_contracts')->nullOnDelete();
            $table->foreignId('grade_id')->nullable()->constrained('pay_grades')->nullOnDelete();
            $table->string('notch', 20)->nullable();
            $table->char('primary_currency', 3);
            $table->decimal('usd_portion_percent', 5, 2)->nullable();
            $table->decimal('zwg_portion_percent', 5, 2)->nullable();
            $table->char('payment_currency', 3);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('status', 20);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->index(['school_id', 'staff_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_pay_structures');
    }
};

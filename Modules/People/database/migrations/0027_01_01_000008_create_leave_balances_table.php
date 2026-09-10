<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book C PPL-04 §2/BR-PPL-04-011. `available_days` is maintained
 * directly by the leave-request lifecycle actions (submit, approve,
 * reject, cancel) rather than derived on read — see
 * `RequestLeaveAction` et al. — so a balance never has to be
 * recomputed from history to answer "how much is left".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('leave_types');
            $table->foreignId('academic_year_id')->constrained();
            $table->decimal('entitlement_days', 5, 1)->default(0);
            $table->decimal('accrued_days', 5, 1)->default(0);
            $table->decimal('carried_forward_days', 5, 1)->default(0);
            $table->decimal('taken_days', 5, 1)->default(0);
            $table->decimal('pending_days', 5, 1)->default(0);
            $table->decimal('available_days', 5, 1)->default(0);

            $table->unique(['school_id', 'staff_id', 'leave_type_id', 'academic_year_id'], 'leave_balances_unique_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_balances');
    }
};

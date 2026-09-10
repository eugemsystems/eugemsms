<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H3 PPL-05 §2/BR-PPL-05-019 — a loan is never over-recovered:
 * `RecordLoanRepaymentAction` refuses an instalment beyond
 * `outstanding_minor`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_loans', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->string('loan_type', 30);
            $table->bigInteger('principal_minor');
            $table->char('currency', 3);
            $table->decimal('interest_rate_percent', 5, 2)->default(0);
            $table->bigInteger('instalment_minor');
            $table->smallInteger('instalment_count');
            $table->date('starts_on');
            $table->bigInteger('outstanding_minor');
            $table->bigInteger('paid_minor')->default(0);
            $table->json('offset_student_ids')->nullable();
            $table->unsignedBigInteger('approval_request_id')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20);

            $table->index(['school_id', 'staff_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_loans');
    }
};

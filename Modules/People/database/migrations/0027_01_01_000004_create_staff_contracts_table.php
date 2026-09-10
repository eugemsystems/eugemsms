<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book C PPL-04 §2/BR-PPL-04-002. `basic_salary_minor` is widened from
 * the spec's literal `BIGINT ENCRYPTED` to `TEXT` for the same reason
 * as `staff`'s encrypted columns — see that migration's docblock.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_contracts', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->string('contract_type', 30);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->tinyInteger('probation_months')->nullable();
            $table->smallInteger('notice_period_days')->default(30);
            $table->decimal('weekly_hours', 5, 2)->nullable();
            $table->text('basic_salary_minor')->nullable();
            $table->char('salary_currency', 3)->nullable();
            $table->string('salary_grade', 30)->nullable();
            $table->string('salary_notch', 20)->nullable();
            $table->foreignId('contract_document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->date('signed_on')->nullable();
            $table->string('status', 20);
            $table->foreignId('renewed_to_contract_id')->nullable()->constrained('staff_contracts')->nullOnDelete();
            $table->date('terminated_on')->nullable();
            $table->string('termination_reason', 120)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['school_id', 'staff_id', 'status']);
            $table->index(['school_id', 'ends_on', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_contracts');
    }
};

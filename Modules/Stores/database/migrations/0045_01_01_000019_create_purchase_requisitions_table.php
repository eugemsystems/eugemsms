<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H1 FIN-08 §2/BR-FIN-08-006. `budget_line_id` is a forward
 * reference to `FIN-11` (built last in this book) — plain nullable
 * column, no FK, matching `FIN-09`'s own established forward-reference
 * pattern for a not-yet-built module. `budget_check_result` is always
 * `not_checked` until `FIN-11` exists to check against; this is a
 * deliberate, honest deferral, not a bug.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_requisitions', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->foreignId('term_id')->constrained();
            $table->string('requisition_number', 40);
            $table->foreignId('department_id')->constrained('departments');
            $table->foreignId('cost_centre_id')->constrained('cost_centres');
            $table->unsignedBigInteger('budget_line_id')->nullable();
            $table->text('justification');
            $table->date('required_by')->nullable();
            $table->string('urgency', 20)->default('normal');
            $table->bigInteger('estimated_total_minor');
            $table->string('currency', 3);
            $table->bigInteger('budget_available_minor')->nullable();
            $table->string('budget_check_result', 20)->nullable();
            $table->string('status', 20);
            $table->unsignedBigInteger('approval_request_id')->nullable();
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->string('rejection_reason', 255)->nullable();
            $table->string('source_type', 40)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'requisition_number']);
            $table->index(['school_id', 'status', 'cost_centre_id'], 'requisitions_status_cc_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_requisitions');
    }
};

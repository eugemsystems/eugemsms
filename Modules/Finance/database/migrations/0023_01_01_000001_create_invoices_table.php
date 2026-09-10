<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book B FIN-03 §2/BR-FIN-03-004/008. `paid_minor`/`credited_minor`/
 * `written_off_minor`/`balance_minor` are caches derived from
 * allocations and journals — nothing else may ever update them
 * outside those four (plus `status`); enforced in `Invoice::booted()`
 * rather than a DB trigger, matching this codebase's existing
 * append-only convention (see `.ai/rules/migrations.md`). `document_id`
 * is a real FK (the `documents` table already exists from `CORE-06`)
 * but nothing in this pass populates it — PDF rendering is a
 * screens-wave concern.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->foreignId('term_id')->constrained();
            $table->string('invoice_number', 80);
            $table->string('invoice_type', 20);
            $table->foreignId('student_id')->constrained();
            $table->string('billed_party_type', 30);
            $table->unsignedBigInteger('billed_party_id');
            $table->decimal('liability_percent', 5, 2)->default(100.00);
            $table->foreignId('assignment_id')->nullable()->constrained('learner_fee_assignments')->nullOnDelete();
            $table->date('issue_date');
            $table->date('due_date');
            $table->bigInteger('gross_minor');
            $table->bigInteger('discount_minor')->default(0);
            $table->bigInteger('net_minor');
            $table->bigInteger('paid_minor')->default(0);
            $table->bigInteger('credited_minor')->default(0);
            $table->bigInteger('written_off_minor')->default(0);
            $table->bigInteger('balance_minor');
            $table->char('currency', 3);
            $table->string('status', 20);
            $table->foreignId('journal_id')->nullable()->constrained('journals')->nullOnDelete();
            $table->unsignedBigInteger('document_id')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->foreignId('replaced_by_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['school_id', 'invoice_number']);
            $table->index(['school_id', 'student_id', 'term_id']);
            $table->index(['school_id', 'billed_party_type', 'billed_party_id', 'status'], 'invoices_billed_party_status_idx');
            $table->index(['school_id', 'status', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book B FIN-03 §2/BR-FIN-03-011. Never a receipt — a credit note
 * posts `Dr Fee Income / Cr Fee Debtors`, the reverse of a normal fee
 * billing entry, and must never appear in a collections total.
 * `approval_request_id` is a real FK (`CORE-07`'s `approval_requests`
 * already exists) but, like `ACA-02`'s cutoff-approval gate, this pass
 * does not wire an actual chain — see `CreateCreditNoteAction`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_notes', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->foreignId('term_id')->constrained();
            $table->string('credit_note_number', 80);
            $table->foreignId('student_id')->constrained();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->string('reason_code', 40);
            $table->text('reason');
            $table->bigInteger('amount_minor');
            $table->bigInteger('applied_minor')->default(0);
            $table->char('currency', 3);
            $table->date('issue_date');
            $table->string('status', 20);
            $table->foreignId('journal_id')->nullable()->constrained('journals')->nullOnDelete();
            $table->unsignedBigInteger('document_id')->nullable();
            $table->foreignId('approval_request_id')->nullable()->constrained('approval_requests')->nullOnDelete();
            $table->foreignId('raised_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');

            $table->unique(['school_id', 'credit_note_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_notes');
    }
};

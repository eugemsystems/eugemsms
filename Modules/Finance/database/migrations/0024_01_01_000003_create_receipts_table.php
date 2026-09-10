<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book B FIN-04 §2/BR-FIN-04-008/010. `till_session_id` is nullable
 * ("null for gateway receipts") — `FIN-05` isn't built yet, so this
 * pass always sets it. `fiscal_receipt_id` (FK to a `fiscal_receipts`
 * table `FIN-13` owns) is omitted — that table doesn't exist, and
 * fiscalisation mechanics are explicitly out of this module's own
 * scope; `fiscalisation_status` alone is kept so `is_fiscalisable`
 * lines can still be flagged `queued` without blocking receipting
 * (BR-FIN-04-021).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->foreignId('term_id')->constrained();
            $table->string('receipt_number', 80);
            $table->foreignId('till_session_id')->nullable()->constrained('till_sessions')->nullOnDelete();
            $table->string('receipt_type', 20);
            $table->string('payer_type', 30);
            $table->unsignedBigInteger('payer_id')->nullable();
            $table->string('payer_name', 200);
            $table->string('payer_phone', 30)->nullable();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->bigInteger('amount_minor');
            $table->char('currency', 3);
            $table->bigInteger('base_amount_minor');
            $table->foreignId('exchange_rate_id')->nullable()->constrained('exchange_rates')->nullOnDelete();
            $table->bigInteger('allocated_minor')->default(0);
            $table->bigInteger('unallocated_minor')->default(0);
            $table->boolean('is_suspense')->default(false);
            $table->timestamp('received_at');
            $table->date('effective_date');
            $table->string('narration', 255)->nullable();
            $table->string('status', 20);
            $table->foreignId('journal_id')->nullable()->constrained('journals')->nullOnDelete();
            $table->unsignedBigInteger('document_id')->nullable();
            $table->string('fiscalisation_status', 20)->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->foreignId('void_journal_id')->nullable()->constrained('journals')->nullOnDelete();
            $table->foreignId('received_by')->constrained('users');
            $table->timestamp('created_at');

            $table->unique(['school_id', 'receipt_number']);
            $table->index(['school_id', 'student_id', 'received_at']);
            $table->index(['school_id', 'is_suspense', 'status']);
            $table->index(['school_id', 'term_id', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};

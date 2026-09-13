<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book B FIN-03 §2/BR-FIN-03-012/013. A waiver reduces the amount owed
 * before it is chased; a write-off recognises an amount as
 * uncollectable after it has been chased — `type` distinguishes them
 * because they post to different accounts and are never conflated.
 * Both go through the same request→approve→post lifecycle and the same
 * `CORE-07`-style two-person gate (approver ≠ requester, enforced in
 * the Action, same pattern as `journal.approve`), so one table serves
 * both rather than duplicating the workflow.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_waivers', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained();
            $table->foreignId('student_id')->constrained();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->string('type', 20);
            $table->bigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('reason_code', 40);
            $table->text('reason');
            $table->unsignedBigInteger('supporting_document_id')->nullable();
            $table->string('status', 20);
            $table->unsignedBigInteger('approval_request_id')->nullable();
            $table->foreignId('journal_id')->nullable()->constrained('journals')->nullOnDelete();
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['school_id', 'student_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_waivers');
    }
};

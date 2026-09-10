<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book B FIN-04 §2/§3 ⭐. `declared_closing` is entered blind;
 * `expected_closing` and `variance` are only ever written by
 * `RevealAndCloseTillSessionAction`, after the declaration already
 * exists — the model doesn't enforce the ordering (it can't tell
 * "declared, not yet revealed" from "always empty" at the DB level),
 * so the actions themselves are the only path that ever populates
 * these columns. `banking_sheet_doc_id` is a plain reference — no
 * banking-sheet document generation is wired in this pass.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('till_sessions', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->foreignId('term_id')->constrained();
            $table->foreignId('till_id')->constrained();
            $table->foreignId('cashier_id')->constrained('users');
            $table->string('session_number', 60);
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->json('opening_float');
            $table->json('declared_closing')->nullable();
            $table->json('expected_closing')->nullable();
            $table->json('variance')->nullable();
            $table->text('variance_reason')->nullable();
            $table->string('status', 20);
            $table->integer('receipt_count')->default(0);
            $table->json('totals_by_tender')->nullable();
            $table->json('totals_by_currency')->nullable();
            $table->foreignId('supervised_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('journal_id')->nullable()->constrained('journals')->nullOnDelete();
            $table->unsignedBigInteger('banking_sheet_doc_id')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'session_number']);
            $table->index(['school_id', 'till_id', 'status']);
            $table->index(['school_id', 'cashier_id', 'opened_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('till_sessions');
    }
};

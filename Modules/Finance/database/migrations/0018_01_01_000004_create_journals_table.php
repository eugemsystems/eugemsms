<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book B FIN-01 §2/BR-FIN-01-011/012. Append-only header table — real
 * enforcement is a model-level guard (`Journal::booted()`), not a DB
 * trigger; see `.ai/rules/migrations.md` for why the DB-grant version of
 * this control is a deployment step, not something a portable migration
 * can safely do against a shared dev database. The guard permits
 * `status`, `reversed_by_journal_id`, and `approved_by` — the spec's own
 * two-column list left no path for `approved_by` to ever be set; see
 * the spec's implementation note at FIN-01 §2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journals', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->foreignId('term_id')->constrained();
            $table->string('journal_number', 80);
            $table->string('journal_type', 40);
            $table->string('narration', 500);
            $table->string('reference', 120)->nullable();
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->date('effective_at');
            $table->timestamp('posted_at', 6);
            $table->boolean('is_prior_period_adjustment')->default(false);
            $table->boolean('is_reversal')->default(false);
            $table->foreignId('reverses_journal_id')->nullable()->constrained('journals')->nullOnDelete();
            $table->foreignId('reversed_by_journal_id')->nullable()->constrained('journals')->nullOnDelete();
            $table->text('reversal_reason')->nullable();
            $table->string('status', 20);
            $table->foreignId('posted_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->char('batch_uuid', 36)->nullable();
            $table->timestamp('created_at');

            $table->unique(['school_id', 'journal_number']);
            $table->index(['school_id', 'term_id', 'effective_at']);
            $table->index(['school_id', 'journal_type', 'effective_at']);
            $table->index(['source_type', 'source_id']);
            $table->index(['school_id', 'batch_uuid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journals');
    }
};

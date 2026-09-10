<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H3 CMP-02 §2/BR-CMP-02-001 ⭐ (AC-CMP-02-002). `data_snapshot`
 * is frozen at generation — a resubmitted return regenerates
 * byte-identically from it, matching this codebase's own
 * historical-document doctrine (Book H3 FIN-12's own
 * `GenerateIncomeStatementAction`). Regenerating later diffs the
 * frozen snapshot against current live data rather than replacing it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('statutory_school_returns', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('return_type', 40);
            $table->string('period_reference', 20);
            $table->date('due_date');
            $table->string('authority', 80);
            $table->json('data_snapshot');
            $table->json('validation_result')->nullable();
            $table->smallInteger('quality_issues')->default(0);
            $table->unsignedBigInteger('export_file_id')->nullable();
            $table->string('status', 20);
            $table->foreignId('generated_by')->nullable()->constrained('users');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users');
            $table->string('acknowledgement_ref', 80)->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'return_type', 'period_reference'], 'statutory_returns_period_unique');
            $table->index(['school_id', 'due_date', 'status'], 'statutory_returns_due_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statutory_school_returns');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H3 FIN-12 §2/§4 ⭐/BR-FIN-12-009/010/011. `results` is a
 * persisted snapshot of `Modules\Core`'s own `ChecklistResult`
 * (CORE-03's real-time, non-persisted engine) — this table is the
 * durable RECORD of a run, not a second checklist engine.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('period_close_checklists', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained();
            $table->string('period_type', 10);
            $table->timestamp('run_at');
            $table->foreignId('run_by')->constrained('users');
            $table->string('overall_status', 30);
            $table->smallInteger('blocking_failures')->default(0);
            $table->smallInteger('warnings')->default(0);
            $table->json('results');
            $table->unsignedBigInteger('report_document_id')->nullable();

            $table->index(['school_id', 'term_id', 'run_at'], 'period_close_checklists_school_term_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('period_close_checklists');
    }
};

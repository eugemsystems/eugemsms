<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H1 FIN-11 §2/§3 ⭐ — the control. One row per committing
 * source (a purchase order today; `contract`/`payroll_commitment`
 * are forward references for sources this book doesn't build).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_commitments', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('budget_line_id')->constrained('budget_lines');
            $table->string('source_type', 40);
            $table->unsignedBigInteger('source_id');
            $table->bigInteger('committed_minor');
            $table->bigInteger('released_minor')->default(0);
            $table->bigInteger('outstanding_minor');
            $table->string('currency', 3);
            $table->timestamp('committed_at');
            $table->timestamp('released_at')->nullable();
            $table->string('status', 20);

            $table->index(['school_id', 'budget_line_id', 'status'], 'commitments_line_status_idx');
            $table->index(['source_type', 'source_id'], 'commitments_source_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_commitments');
    }
};

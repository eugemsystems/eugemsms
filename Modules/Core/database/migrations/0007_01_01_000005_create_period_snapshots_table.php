<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book A CORE-03 §2 — the forensic anchor. BR-CORE-03-015/016: hash-chained,
 * append-only, never deleted or edited, excluded from every cascade delete
 * (no `cascadeOnDelete()` here — see `down()`, deliberately not reversible
 * by dropping the school).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('period_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained();
            $table->foreignId('academic_year_id')->constrained();
            $table->foreignId('term_id')->constrained();
            $table->string('snapshot_type', 30);
            $table->timestamp('taken_at');
            $table->foreignId('taken_by')->nullable()->constrained('users');
            $table->json('payload');
            $table->char('payload_hash', 64);
            $table->char('previous_hash', 64)->nullable();
            $table->json('row_counts');

            $table->index(['school_id', 'term_id', 'snapshot_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('period_snapshots');
    }
};

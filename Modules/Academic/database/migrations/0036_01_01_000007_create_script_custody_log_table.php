<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book E ACA-07 §2/BR-ACA-07-012 — APPEND-ONLY. Every handover writes
 * a row here before the batch's own `current_holder_staff_id`/`status`
 * ever changes; a count mismatch is recorded, never silently
 * corrected. Model-level guard mirrors `LegacyCalaRecord`'s.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('script_custody_log', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained('script_batches');
            $table->foreignId('from_staff_id')->nullable()->constrained('staff');
            $table->foreignId('to_staff_id')->nullable()->constrained('staff');
            $table->string('action', 30);
            $table->smallInteger('script_count');
            $table->text('discrepancy_note')->nullable();
            $table->timestamp('occurred_at');
            $table->foreignId('recorded_by')->constrained('users');

            $table->index(['school_id', 'batch_id'], 'script_custody_batch_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('script_custody_log');
    }
};

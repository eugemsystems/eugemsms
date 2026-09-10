<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book E ACA-07 §2/§3 ⭐ — the chain-of-custody header row.
 * `current_holder_staff_id` is denormalised for a fast "who has this
 * batch right now" lookup; `script_custody_log` is the append-only
 * ledger it is derived from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('script_batches', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('paper_id')->constrained('examination_papers');
            $table->string('batch_reference', 60);
            $table->foreignId('venue_id')->nullable()->constrained();
            $table->smallInteger('script_count');
            $table->smallInteger('expected_count');
            $table->string('status', 20);
            $table->foreignId('current_holder_staff_id')->nullable()->constrained('staff');

            $table->unique(['school_id', 'batch_reference'], 'script_batches_reference_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('script_batches');
    }
};

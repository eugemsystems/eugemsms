<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book E ACA-07 §2/BR-ACA-07-006/007. No `ulid` — always reached
 * through its paper, mirroring `RubricCriterion`'s reasoning.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('examination_seatings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('paper_id')->constrained('examination_papers');
            $table->foreignId('candidate_id')->constrained('examination_candidates');
            $table->foreignId('venue_id')->constrained();
            $table->smallInteger('row_number')->nullable();
            $table->string('seat_number', 20);
            $table->boolean('attended')->nullable();
            $table->time('arrival_time')->nullable();
            $table->string('notes', 255)->nullable();

            $table->unique(['paper_id', 'candidate_id'], 'exam_seatings_candidate_unique');
            $table->unique(['paper_id', 'venue_id', 'seat_number'], 'exam_seatings_seat_unique');
            $table->index(['school_id', 'paper_id', 'venue_id'], 'exam_seatings_venue_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('examination_seatings');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book E ACA-03 §2/BR-ACA-03-013/015 — a versioned published
 * schedule. `generation_run_id` has no FK constraint yet — created
 * after `timetable_generation_runs`, which itself references
 * `timetables`, so the two tables have a genuine mutual reference;
 * breaking the cycle here (plain column, no constraint) rather than
 * making `timetable_generation_runs` nullable-then-backfilled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timetables', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->foreignId('term_id')->constrained();
            $table->foreignId('structure_id')->constrained('period_structures');
            $table->string('name', 120);
            $table->smallInteger('version')->default(1);
            $table->string('status', 20);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->unsignedBigInteger('generation_run_id')->nullable();
            $table->smallInteger('hard_violations')->default(0);
            $table->smallInteger('soft_violations')->default(0);
            $table->decimal('quality_score', 5, 2)->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['school_id', 'term_id', 'status'], 'timetables_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetables');
    }
};

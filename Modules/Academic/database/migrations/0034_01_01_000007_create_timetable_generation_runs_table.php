<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book E ACA-03 §2/§4 ⭐/BR-ACA-03-005. `unplaced_requirements` is
 * the honesty mechanism: a requirement the generator could not place
 * is named here, never silently dropped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timetable_generation_runs', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('timetable_id')->constrained()->cascadeOnDelete();
            $table->string('algorithm', 30);
            $table->json('parameters')->nullable();
            $table->string('status', 20);
            $table->integer('iterations')->default(0);
            $table->decimal('best_score', 10, 2)->nullable();
            $table->smallInteger('hard_violations')->nullable();
            $table->json('soft_violation_detail')->nullable();
            $table->json('unplaced_requirements')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('requested_by')->constrained('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_generation_runs');
    }
};

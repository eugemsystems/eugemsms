<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H2 OPS-06 §2/§3 ⭐/BR-OPS-06-008/009/010.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emergency_drills', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained();
            $table->string('drill_type', 30);
            $table->timestamp('conducted_at');
            $table->boolean('is_announced')->default(false);
            $table->smallInteger('expected_headcount');
            $table->smallInteger('mustered_headcount')->nullable();
            $table->smallInteger('unaccounted_count')->nullable();
            $table->integer('evacuation_seconds')->nullable();
            $table->json('assembly_points')->nullable();
            $table->text('findings')->nullable();
            $table->text('actions_required')->nullable();
            $table->foreignId('conducted_by')->constrained('users');

            $table->index(['school_id', 'term_id', 'conducted_at'], 'emergency_drills_term_conducted_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emergency_drills');
    }
};

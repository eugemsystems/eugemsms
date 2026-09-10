<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book F BRD-05 §2/BR-BRD-05-005/006 — per learner per cycle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laundry_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cycle_id')->constrained('laundry_cycles');
            $table->foreignId('student_id')->constrained();
            $table->smallInteger('items_out');
            $table->smallInteger('items_back')->nullable();
            $table->string('missing_description', 255)->nullable();
            $table->boolean('resolved')->default(false);

            $table->index(['school_id', 'cycle_id'], 'laundry_items_cycle_idx');
            $table->index(['school_id', 'student_id'], 'laundry_items_student_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laundry_items');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book F BRD-02 §2/BR-BRD-02-017 — APPEND-ONLY. `exeat_id` is a
 * forward reference to `BRD-03` (no FK yet — that module doesn't
 * exist in this pass).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movement_log', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained();
            $table->foreignId('checkpoint_id')->constrained('movement_checkpoints');
            $table->string('direction', 10);
            $table->timestamp('occurred_at', 3);
            $table->string('method', 20);
            $table->foreignId('recorded_by')->nullable()->constrained('users');
            $table->unsignedBigInteger('exeat_id')->nullable();
            $table->boolean('is_authorised')->default(true);
            $table->string('note', 255)->nullable();

            $table->index(['school_id', 'student_id', 'occurred_at'], 'movement_log_student_idx');
            $table->index(['school_id', 'checkpoint_id', 'occurred_at'], 'movement_log_checkpoint_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movement_log');
    }
};

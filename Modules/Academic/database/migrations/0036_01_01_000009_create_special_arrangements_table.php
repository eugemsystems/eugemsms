<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book E ACA-07 §2/BR-ACA-07-009. Carries forward between sessions
 * until expired or withdrawn — never re-entered per session.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('special_arrangements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('session_id')->constrained('examination_sessions');
            $table->foreignId('student_id')->constrained();
            $table->string('arrangement_type', 40);
            $table->smallInteger('extra_time_percent')->nullable();
            $table->text('justification');
            $table->unsignedBigInteger('supporting_document_id')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->json('applies_to_papers')->nullable();
            $table->string('status', 20);

            $table->index(['school_id', 'session_id', 'student_id'], 'special_arrangements_student_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('special_arrangements');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book A CORE-03 §2. BR-CORE-03-022: only one `running` row per school at
 * a time — enforced in `ACT-InitiateRollover`/`ACT-ExecuteRollover`, not
 * at the schema level (a partial unique index on `status = 'running'`
 * isn't portable across MySQL and PostgreSQL without a generated column,
 * and the action-level atomic lock this rule actually specifies is
 * simpler and works on both).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('period_rollovers', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_term_id')->constrained('terms');
            $table->foreignId('to_term_id')->constrained('terms');
            $table->string('status', 20)->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('initiated_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->json('validation_report')->nullable();
            $table->json('step_log')->nullable();
            $table->json('exception_report')->nullable();
            // No FK yet: `documents` belongs to CORE-06, built after
            // CORE-03 — same forward-reserved-column pattern as
            // school_classes.room_id (CORE-02) for Estates' `rooms`.
            $table->unsignedBigInteger('report_document_id')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('period_rollovers');
    }
};

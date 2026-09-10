<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book C PPL-01 §3 ⭐/BR-PPL-01-004. Append-only — this is the billing
 * truth `FIN-02` prorates against, never `students`' current row.
 * `effective_from` (when the change took effect) is deliberately
 * distinct from `changed_at` (when the school recorded it).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_attribute_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->foreignId('term_id')->constrained();
            $table->string('attribute', 40);
            $table->string('old_value', 80)->nullable();
            $table->string('new_value', 80);
            $table->date('effective_from');
            $table->string('reason_code', 40)->nullable();
            $table->text('reason')->nullable();
            $table->boolean('triggers_rebilling')->default(false);
            $table->string('rebilling_status', 20)->nullable();
            $table->foreignId('changed_by')->constrained('users');
            $table->timestamp('changed_at');

            $table->index(['school_id', 'student_id', 'effective_from'], 'attribute_changes_student_effective_idx');
            $table->index(['school_id', 'rebilling_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_attribute_changes');
    }
};

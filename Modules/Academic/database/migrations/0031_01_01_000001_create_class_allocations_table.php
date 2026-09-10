<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book D ACA-02 §2/BR-ACA-02-014. Distinct from `teaching_groups` — a
 * form class, per term, dated. Mid-term movement supersedes rather than
 * overwrites (the previous row's `effective_to`/`status` change; a new
 * row is inserted), so the full history stays queryable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_allocations', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->foreignId('term_id')->constrained();
            $table->foreignId('student_id')->constrained();
            $table->foreignId('class_id')->constrained('school_classes');
            $table->string('allocation_type', 20);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('status', 20);
            $table->foreignId('allocated_by')->constrained('users');
            $table->foreignId('confirmed_by')->nullable()->constrained('users');
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'term_id', 'student_id', 'effective_from'], 'class_allocations_unique');
            $table->index(['school_id', 'term_id', 'class_id', 'status'], 'class_allocations_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_allocations');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book C PPL-04 §2/BR-PPL-04-020 ⭐ — confidential by default, visible
 * only to the head, the deputy, and the case handler; every access is
 * logged. This table carries no `case_number` uniqueness constraint
 * beyond the school scope in the spec's own SQL, so none is added
 * here either.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_disciplinary_cases', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->string('case_number', 40);
            $table->string('category', 60);
            $table->text('description');
            $table->date('incident_date');
            $table->foreignId('reported_by')->constrained('users');
            $table->string('stage', 30);
            $table->string('outcome', 40)->nullable();
            $table->date('outcome_date')->nullable();
            $table->boolean('is_confidential')->default(true);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['school_id', 'staff_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_disciplinary_cases');
    }
};

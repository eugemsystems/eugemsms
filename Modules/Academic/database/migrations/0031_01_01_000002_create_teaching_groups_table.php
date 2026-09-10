<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book D ACA-02 §2/BR-ACA-02-012/013. A set for one subject, independent
 * of form class (e.g. a maths set that pulls learners from three form
 * classes). `room_id` has no FK constraint yet — rooms/facilities are
 * Book H2's own module, not built in this pass — same pattern as
 * `learner_fee_assignments.invoice_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teaching_groups', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->foreignId('term_id')->constrained();
            $table->foreignId('subject_id')->constrained();
            $table->foreignId('grade_level_id')->constrained();
            $table->string('code', 30);
            $table->string('name', 120);
            $table->string('set_level', 20)->nullable();
            $table->foreignId('teacher_staff_id')->nullable()->constrained('staff');
            $table->unsignedBigInteger('room_id')->nullable();
            $table->smallInteger('capacity')->nullable();
            $table->smallInteger('current_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['school_id', 'term_id', 'code'], 'teaching_groups_unique');
            $table->index(['school_id', 'term_id', 'subject_id'], 'teaching_groups_subject_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teaching_groups');
    }
};

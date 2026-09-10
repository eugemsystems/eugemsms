<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book D ACA-05 §2/§3 ⭐ — overall per learner per term, rebuilt by
 * `ComputeTermResultsAction`. `report_document_id` has no FK
 * constraint — `Document` lives in Core and this row is written long
 * before a report card is ever generated (`status` starts `computed`,
 * long before `report_card_runs` exists for the term).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('term_results', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->foreignId('term_id')->constrained();
            $table->foreignId('student_id')->constrained();
            $table->foreignId('class_id')->constrained('school_classes');
            $table->tinyInteger('subjects_taken')->default(0);
            $table->tinyInteger('subjects_passed')->default(0);
            $table->decimal('total_marks', 8, 2)->nullable();
            $table->decimal('average_percent', 5, 2)->nullable();
            $table->decimal('total_points', 6, 2)->nullable();
            $table->smallInteger('aggregate')->nullable();
            $table->smallInteger('class_position')->nullable();
            $table->smallInteger('class_size')->nullable();
            $table->smallInteger('level_position')->nullable();
            $table->smallInteger('level_size')->nullable();
            $table->decimal('attendance_percent', 5, 2)->nullable();
            $table->string('conduct_grade', 20)->nullable();
            $table->text('class_teacher_comment')->nullable();
            $table->text('head_comment')->nullable();
            $table->string('promotion_recommendation', 30)->nullable();
            $table->string('status', 20);
            $table->string('withheld_reason', 60)->nullable();
            $table->unsignedBigInteger('report_document_id')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');

            $table->unique(['school_id', 'term_id', 'student_id'], 'term_results_unique');
            $table->index(['school_id', 'term_id', 'class_id', 'status'], 'term_results_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('term_results');
    }
};

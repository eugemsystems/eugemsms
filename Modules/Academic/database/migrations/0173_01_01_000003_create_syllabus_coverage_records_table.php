<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book K ACA-11 §2/BR-ACA-11-002. One row per planned topic, created
 * alongside its scheme of work — `actual_delivered_on` starts null and
 * is filled in as the teacher actually covers each topic.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('syllabus_coverage_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scheme_of_work_id')->constrained('schemes_of_work')->cascadeOnDelete();
            $table->smallInteger('planned_topic_index');
            $table->date('actual_delivered_on')->nullable();
            $table->string('variance_note', 255)->nullable();
            $table->timestamps();

            $table->unique(['scheme_of_work_id', 'planned_topic_index'], 'syllabus_coverage_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('syllabus_coverage_records');
    }
};

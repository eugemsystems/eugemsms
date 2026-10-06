<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book C PPL-02 §2. An applicant sitting an entrance exam, with their marks and rank.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entrance_exam_candidates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_id')->constrained('entrance_exams')->cascadeOnDelete();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->string('candidate_number', 30);
            $table->string('seat_number', 20)->nullable();
            $table->boolean('attended')->nullable();
            $table->json('marks')->nullable();
            $table->decimal('total_mark', 6, 2)->nullable();
            $table->decimal('percentage', 5, 2)->nullable();
            $table->unsignedSmallInteger('rank_in_exam')->nullable();
            $table->unique(['exam_id', 'application_id'], 'entrance_exam_candidates_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entrance_exam_candidates');
    }
};

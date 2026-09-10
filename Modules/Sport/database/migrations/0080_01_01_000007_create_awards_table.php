<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H2 OPS-07 §2. BR-OPS-07-010's "carries into the alumni record
 * on graduation" is a documented Book K deferral — no alumni module
 * exists yet in this codebase.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('awards', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->foreignId('student_id')->constrained();
            $table->string('award_type', 30);
            $table->foreignId('activity_id')->nullable()->constrained('activities');
            $table->string('title', 200);
            $table->text('citation')->nullable();
            $table->date('awarded_on');
            $table->foreignId('awarded_by')->constrained('users');
            $table->boolean('appears_on_report_card')->default(true);
            $table->boolean('appears_on_transcript')->default(true);

            $table->index(['school_id', 'student_id', 'academic_year_id'], 'awards_school_student_year_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('awards');
    }
};

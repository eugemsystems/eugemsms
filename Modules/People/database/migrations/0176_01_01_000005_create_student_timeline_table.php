<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book C PPL-01 §2. A denormalised feed of what happened to a learner, for the profile timeline. Append-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_timeline', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained();
            $table->foreignId('term_id')->nullable()->constrained();
            $table->string('event_category', 30);
            $table->string('event_type', 60);
            $table->string('title', 200);
            $table->string('summary', 500)->nullable();
            $table->string('severity', 20)->nullable();
            $table->string('source_type', 255)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->boolean('is_visible_to_guardian')->default(false);
            $table->timestamp('occurred_at');
            $table->foreignId('recorded_by')->nullable()->constrained('users');
            $table->index(['school_id', 'student_id', 'occurred_at']);
            $table->index(['school_id', 'student_id', 'event_category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_timeline');
    }
};

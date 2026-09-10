<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H2 OPS-07 §2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->foreignId('activity_id')->constrained();
            $table->string('name', 120);
            $table->string('age_group', 20)->nullable();
            $table->string('level', 20)->nullable();
            $table->foreignId('coach_staff_id')->nullable()->constrained('staff');
            $table->foreignId('captain_student_id')->nullable()->constrained('students');
            $table->boolean('is_active')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};

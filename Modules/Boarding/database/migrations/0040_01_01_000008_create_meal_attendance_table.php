<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book F BRD-04 §2/BR-BRD-04-015.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meal_attendance', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meal_service_id')->constrained('meal_services');
            $table->foreignId('student_id')->constrained();
            $table->boolean('attended')->default(true);
            $table->boolean('special_meal_served')->default(false);
            $table->timestamp('recorded_at');
            $table->string('method', 20);

            $table->unique(['meal_service_id', 'student_id'], 'meal_attendance_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_attendance');
    }
};

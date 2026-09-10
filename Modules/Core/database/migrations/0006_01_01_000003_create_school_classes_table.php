<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book A CORE-02 §2 — streams. BR-CORE-02-004: a class always belongs to
 * exactly one academic year; classes are created fresh each year, never
 * carried across.
 *
 * `room_id` has no foreign key yet: `rooms` belongs to Book H2 (Estates),
 * built long after CORE-02 in the module order. The column is reserved
 * now so the eventual Estates migration only needs to add the constraint,
 * not the column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_classes', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grade_level_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 80);
            $table->string('stream_label', 30)->nullable();
            $table->foreignId('class_teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assistant_teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('room_id')->nullable();
            $table->smallInteger('capacity')->default(40);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['school_id', 'academic_year_id', 'code']);
            $table->index(['school_id', 'academic_year_id', 'grade_level_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_classes');
    }
};

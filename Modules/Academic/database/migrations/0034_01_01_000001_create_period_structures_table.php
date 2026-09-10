<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book E ACA-03 §2/BR-ACA-03-001/002 — the shape of a school day, per
 * section per academic year (primary and secondary never share one).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('period_structures', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->nullable()->constrained('school_sections')->nullOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->string('name', 120);
            $table->string('cycle_type', 20);
            $table->tinyInteger('cycle_days')->default(5);
            $table->json('day_labels');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['school_id', 'academic_year_id', 'section_id', 'name'], 'period_structures_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('period_structures');
    }
};

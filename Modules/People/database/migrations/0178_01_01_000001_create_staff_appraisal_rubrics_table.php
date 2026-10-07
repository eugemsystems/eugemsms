<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book C PPL-04 — the structured appraisal rubric this book's own `staff_appraisals`
 * schema left as free-form JSON. `criteria` uses the same criterion/descriptor-levels
 * shape as ACA-06's project rubrics and ACA-11's observation rubrics — a conceptual
 * reuse, not a literal shared table, matching `observation_rubrics`' own precedent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_appraisal_rubrics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->json('criteria');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_appraisal_rubrics');
    }
};

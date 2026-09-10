<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book A CORE-06 §2. `academic_year_id`/`term_id` are null for a
 * document type that isn't period-scoped (e.g. a purchase order series
 * that never resets).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('numbering_series', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 40);
            $table->foreignId('academic_year_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('term_id')->nullable()->constrained()->nullOnDelete();
            $table->string('pattern', 120);
            $table->string('prefix', 20)->nullable();
            $table->unsignedBigInteger('next_sequence')->default(1);
            $table->tinyInteger('sequence_padding')->unsigned()->default(6);
            $table->string('reset_policy', 20)->default('never');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['school_id', 'document_type', 'academic_year_id', 'term_id'], 'numbering_series_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('numbering_series');
    }
};

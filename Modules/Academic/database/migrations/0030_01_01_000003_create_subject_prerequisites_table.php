<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book D ACA-01 §2/BR-ACA-01-012.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subject_prerequisites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects');
            $table->foreignId('prerequisite_subject_id')->constrained('subjects');
            $table->string('minimum_grade', 10)->nullable();
            $table->string('examination', 60)->nullable();
            $table->string('severity', 20);

            $table->unique(['school_id', 'subject_id', 'prerequisite_subject_id'], 'subject_prerequisites_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_prerequisites');
    }
};

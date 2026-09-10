<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book G BRD-06 §2 — APPEND-ONLY vitals chart for one admission.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_observations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admission_id')->constrained('sick_bay_admissions');
            $table->timestamp('observed_at');
            $table->decimal('temperature_c', 4, 1)->nullable();
            $table->smallInteger('pulse_bpm')->nullable();
            $table->smallInteger('respiration_rate')->nullable();
            $table->string('blood_pressure', 20)->nullable();
            $table->smallInteger('oxygen_saturation')->nullable();
            $table->tinyInteger('pain_score')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('observed_by')->constrained('users');

            $table->index(['admission_id', 'observed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_observations');
    }
};

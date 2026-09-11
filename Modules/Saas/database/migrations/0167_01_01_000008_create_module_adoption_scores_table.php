<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book J SAA-03 §2/§3 ⭐/BR-SAA-03-006 ⭐ — derived from real recorded
 * activity, never a self-report. See `ModuleAdoptionSignalRegistry`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_adoption_scores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('module_code', 20);
            $table->char('period_month', 7);
            $table->string('activity_signal', 60);
            $table->integer('activity_count')->default(0);
            $table->boolean('is_actively_used')->default(false);
            $table->timestamps();

            $table->unique(['school_id', 'module_code', 'period_month'], 'module_adoption_school_module_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_adoption_scores');
    }
};

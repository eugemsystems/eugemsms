<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book J INT-02 §2/BR-INT-02-002/003.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpi_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('kpi_key', 60);
            $table->foreignId('academic_year_id')->constrained();
            $table->decimal('target_value', 14, 2);
            $table->decimal('warning_threshold_percent', 5, 2)->default(90);
            $table->timestamps();

            $table->foreign('kpi_key')->references('key')->on('kpi_definitions');
            $table->unique(['school_id', 'kpi_key', 'academic_year_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_targets');
    }
};

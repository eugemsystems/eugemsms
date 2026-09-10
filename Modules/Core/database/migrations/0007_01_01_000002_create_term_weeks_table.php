<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book A CORE-03 §2/§4 — `ACT-GenerateTermWeeks`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('term_weeks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('week_number')->unsigned();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('is_teaching_week')->default(true);
            $table->string('label', 60)->nullable();
            $table->timestamps();

            $table->unique(['term_id', 'week_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('term_weeks');
    }
};

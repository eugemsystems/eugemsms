<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H3 PPL-05 §2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pay_grade_notches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grade_id')->constrained('pay_grades')->cascadeOnDelete();
            $table->string('notch', 20);
            $table->bigInteger('basic_salary_minor');
            $table->char('currency', 3);
            $table->date('effective_from');

            $table->unique(['grade_id', 'notch', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pay_grade_notches');
    }
};

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
        Schema::create('pay_grades', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 120);
            $table->string('category', 30);
            $table->bigInteger('min_salary_minor')->nullable();
            $table->bigInteger('max_salary_minor')->nullable();
            $table->char('currency', 3);
            $table->string('nec_grade_reference', 40)->nullable();
            $table->boolean('is_active')->default(true);

            $table->unique(['school_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pay_grades');
    }
};

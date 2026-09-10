<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book C PPL-04 §2/BR-PPL-04-015.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('duty_rosters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->foreignId('term_id')->constrained();
            $table->string('duty_type', 40);
            $table->string('name', 120);
            $table->string('rotation_pattern', 20);
            $table->json('eligible_categories')->nullable();
            $table->boolean('is_active')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('duty_rosters');
    }
};

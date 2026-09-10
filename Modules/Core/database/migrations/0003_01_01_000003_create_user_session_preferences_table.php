<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a user's active (school, academic_year, term) is remembered
 * between requests. `SetSchoolContext`/`SetSessionContext` read this;
 * `ACT-SwitchSession` (CORE-03) writes it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_session_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('term_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'school_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_session_preferences');
    }
};

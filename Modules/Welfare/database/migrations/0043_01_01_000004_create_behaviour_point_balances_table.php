<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book G BRD-07 §2/BR-BRD-07-002/015 — CACHE, rebuilt from
 * `behaviour_records`. `conduct_grade` feeds `ACA-05`'s term result.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('behaviour_point_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained();
            $table->foreignId('term_id')->constrained();
            $table->smallInteger('merit_points')->default(0);
            $table->smallInteger('demerit_points')->default(0);
            $table->smallInteger('net_points')->default(0);
            $table->smallInteger('record_count')->default(0);
            $table->string('conduct_grade', 20)->nullable();
            $table->timestamp('rebuilt_at')->nullable();

            $table->unique(['school_id', 'student_id', 'term_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('behaviour_point_balances');
    }
};

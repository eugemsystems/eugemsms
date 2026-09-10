<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book E ACA-03 §2/§5/BR-ACA-03-021 — one-off changes to the pattern.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timetable_exceptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained();
            $table->date('exception_date');
            $table->string('exception_type', 30);
            $table->foreignId('alternative_structure_id')->nullable()->constrained('period_structures');
            $table->string('affected_scope', 20);
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->string('reason', 255);
            $table->boolean('suppresses_attendance')->default(false);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('created_at')->nullable();

            $table->unique(['school_id', 'exception_date', 'affected_scope', 'scope_id'], 'timetable_exceptions_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_exceptions');
    }
};

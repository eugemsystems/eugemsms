<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book A CORE-03 §2. Only the columns Part 1's session context and
 * PeriodGuard need are seeded here — week generation, holidays, snapshots,
 * and the roll-over engine are built with the rest of CORE-03.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name', 30);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('is_current')->default(false);
            $table->string('academic_state', 20)->default('planned');
            $table->string('financial_state', 20)->default('planned');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['school_id', 'name']);
            $table->index(['school_id', 'is_current']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_years');
    }
};

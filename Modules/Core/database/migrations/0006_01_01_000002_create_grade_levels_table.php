<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book A CORE-02 §2. BR-CORE-02-003: `ordinal` is unique per school and
 * defines the promotion sequence — promotion moves a learner from ordinal
 * n to n+1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grade_levels', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained('school_sections')->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 60);
            $table->smallInteger('ordinal');
            $table->boolean('is_exam_level')->default(false);
            $table->boolean('is_entry_level')->default(false);
            $table->boolean('is_exit_level')->default(false);
            $table->smallInteger('capacity')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['school_id', 'code']);
            $table->unique(['school_id', 'ordinal']);
            $table->index(['school_id', 'ordinal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_levels');
    }
};

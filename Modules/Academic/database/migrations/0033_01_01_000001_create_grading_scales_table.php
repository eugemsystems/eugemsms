<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book D ACA-05 §2/BR-ACA-05-001/003. Pure data — a new scale, or a
 * school's own scale, needs no deployment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grading_scales', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('framework_id')->nullable()->constrained('curriculum_frameworks')->nullOnDelete();
            $table->string('code', 30);
            $table->string('name', 120);
            $table->string('scale_type', 20);
            $table->boolean('lower_is_better')->default(false);
            $table->string('pass_grade', 10)->nullable();
            $table->json('is_default_for_level')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['school_id', 'code'], 'grading_scales_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grading_scales');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book C PPL-04 §2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_appraisals', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->string('cycle', 30);
            $table->foreignId('appraiser_staff_id')->constrained('staff');
            $table->json('self_assessment')->nullable();
            $table->json('appraiser_assessment')->nullable();
            $table->json('objectives')->nullable();
            $table->string('overall_rating', 30)->nullable();
            $table->text('development_plan')->nullable();
            $table->text('staff_comments')->nullable();
            $table->string('status', 20);
            $table->timestamp('signed_off_at')->nullable();

            $table->index(['school_id', 'staff_id', 'academic_year_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_appraisals');
    }
};

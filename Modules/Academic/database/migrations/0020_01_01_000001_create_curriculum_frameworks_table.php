<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book D ACA-01 §2. Versioned by `effective_from`/`effective_to`
 * (BR-ACA-01-002) — a record created under a framework keeps that
 * reference permanently, so historical documents render under the
 * rules that were current when they were created (BR-ACA-01-003).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curriculum_frameworks', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 150);
            $table->string('authority', 80);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('status', 20);
            $table->string('continuous_assessment_model', 20);
            $table->string('reference_circular', 120)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['school_id', 'code']);
            $table->index(['school_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curriculum_frameworks');
    }
};

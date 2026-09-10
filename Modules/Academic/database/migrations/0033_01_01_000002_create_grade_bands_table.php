<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book D ACA-05 §2/BR-ACA-05-002. Contiguity/non-overlap across
 * 0-100 is enforced at save time by `CreateGradeBandAction`, not by a
 * DB constraint — the check needs every sibling band for the scale,
 * which a column constraint can't express.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grade_bands', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grading_scale_id')->constrained()->cascadeOnDelete();
            $table->string('grade', 10);
            $table->string('descriptor', 80)->nullable();
            $table->decimal('min_percent', 5, 2);
            $table->decimal('max_percent', 5, 2);
            $table->decimal('points', 5, 2)->nullable();
            $table->boolean('is_pass')->default(true);
            $table->char('colour', 7)->nullable();
            $table->smallInteger('sort_order');

            $table->unique(['grading_scale_id', 'grade'], 'grade_bands_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_bands');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book C PPL-01 §2/BR-PPL-01-018. Symmetric sibling links: each link is stored in both directions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_siblings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sibling_student_id')->constrained('students')->cascadeOnDelete();
            $table->string('relationship', 20);
            $table->unsignedTinyInteger('birth_order')->nullable();
            $table->foreignId('linked_by')->nullable()->constrained('users');
            $table->timestamp('created_at')->nullable();
            $table->unique(['school_id', 'student_id', 'sibling_student_id'], 'student_siblings_unique');
            $table->index(['school_id', 'sibling_student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_siblings');
    }
};

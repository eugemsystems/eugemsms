<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book F BRD-01 §2/BR-BRD-01-010.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hostel_waiting_list', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->foreignId('term_id')->constrained();
            $table->foreignId('student_id')->constrained();
            $table->foreignId('preferred_hostel_id')->nullable()->constrained('hostels');
            $table->decimal('priority_score', 6, 2)->nullable();
            $table->smallInteger('position')->nullable();
            $table->string('reason', 255)->nullable();
            $table->string('status', 20);
            $table->timestamp('added_at');

            $table->unique(['school_id', 'term_id', 'student_id'], 'hostel_waiting_list_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hostel_waiting_list');
    }
};

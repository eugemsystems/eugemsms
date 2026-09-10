<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book G BRD-06 §2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('immunisations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained();
            $table->string('vaccine', 120);
            $table->tinyInteger('dose_number')->nullable();
            $table->date('administered_on')->nullable();
            $table->string('administered_by', 150)->nullable();
            $table->string('batch_number', 60)->nullable();
            $table->date('next_due_on')->nullable();
            $table->unsignedBigInteger('certificate_file_id')->nullable();
            $table->string('status', 20);
            $table->string('decline_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['school_id', 'student_id']);
            $table->index(['school_id', 'next_due_on', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('immunisations');
    }
};

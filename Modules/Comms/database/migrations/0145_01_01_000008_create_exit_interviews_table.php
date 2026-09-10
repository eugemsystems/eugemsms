<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book I COM-08 §2 ⭐/BR-COM-08-007.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exit_interviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained();
            $table->foreignId('guardian_id')->nullable()->constrained('guardians');
            $table->timestamp('requested_at');
            $table->timestamp('completed_at')->nullable();
            $table->string('primary_reason', 60)->nullable();
            $table->text('detail')->nullable();
            $table->boolean('would_recommend')->nullable();
            $table->string('response_source', 20)->nullable();
            $table->timestamps();

            $table->index(['school_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exit_interviews');
    }
};

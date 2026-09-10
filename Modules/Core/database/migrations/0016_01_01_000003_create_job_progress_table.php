<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book A CORE-12 §2/BR-CORE-12-005. User-visible long-running work,
 * polled by the UI.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_progress', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->string('job_type', 80);
            $table->string('title', 200);
            $table->string('status', 20)->default('queued');
            $table->integer('total_steps')->nullable();
            $table->integer('completed_steps')->default(0);
            $table->string('current_message', 255)->nullable();
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_progress');
    }
};

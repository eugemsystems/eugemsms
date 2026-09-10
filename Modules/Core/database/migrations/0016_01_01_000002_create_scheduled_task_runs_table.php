<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_task_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('task_id')->constrained('scheduled_tasks')->cascadeOnDelete();
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20);
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->integer('duration_ms')->nullable();
            $table->text('output')->nullable();
            $table->text('error')->nullable();

            $table->index(['task_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_task_runs');
    }
};

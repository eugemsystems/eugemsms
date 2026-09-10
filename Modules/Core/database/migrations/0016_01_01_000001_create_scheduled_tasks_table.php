<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book A CORE-12 §2. "Seeded from code" — mirrors
 * `ScheduledTaskRegistry`, same split as `file_categories`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_tasks', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 80)->unique();
            $table->string('module_code', 20);
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('command', 255);
            $table->string('schedule_expression', 60);
            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_per_school')->default(false);
            $table->integer('timeout_seconds')->default(300);
            $table->boolean('alert_on_failure')->default(true);
            $table->integer('alert_if_not_run_within_minutes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_tasks');
    }
};

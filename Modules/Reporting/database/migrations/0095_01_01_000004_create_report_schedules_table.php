<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H3 FIN-12 §2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('report_definition_id')->constrained('report_definitions');
            $table->string('name', 150);
            $table->string('frequency', 20);
            $table->json('parameters')->nullable();
            $table->json('recipients');
            $table->string('format', 20);
            $table->timestamp('next_run_at')->nullable();
            $table->boolean('is_active')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_schedules');
    }
};

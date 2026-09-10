<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H3 CMP-03 §2/BR-CMP-03-005/007. `table_names` is what
 * `CheckRetentionScheduleCoverageAction` matches against every table
 * `PersonalDataTableRegistry` knows about.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retention_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('record_class', 60);
            $table->json('table_names');
            $table->decimal('retention_years', 5, 2);
            $table->string('retention_trigger', 40);
            $table->string('disposal_method', 30);
            $table->string('legal_basis', 255);
            $table->boolean('requires_review')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['school_id', 'record_class'], 'retention_schedules_record_class_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retention_schedules');
    }
};

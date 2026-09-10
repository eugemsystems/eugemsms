<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book G BRD-07 §2. `approval_chain_id` is a forward reference to
 * `CORE-07` — not built, kept as a plain nullable column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sanction_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 120);
            $table->tinyInteger('severity_level');
            $table->unsignedBigInteger('approval_chain_id')->nullable();
            $table->boolean('requires_guardian_meeting')->default(false);
            $table->boolean('requires_committee')->default(false);
            $table->boolean('removes_from_lessons')->default(false);
            $table->boolean('removes_from_campus')->default(false);
            $table->smallInteger('max_duration_days')->nullable();
            $table->boolean('appealable')->default(true);
            $table->smallInteger('appeal_window_days')->default(5);
            $table->boolean('is_active')->default(true);

            $table->unique(['school_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanction_types');
    }
};

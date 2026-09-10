<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book G BRD-07 §2/BR-BRD-07-003 — `is_automatic` defaults false: a
 * trigger suggests, a human decides.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('behaviour_trigger_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('trigger_type', 30);
            $table->smallInteger('demerit_threshold')->nullable();
            $table->smallInteger('window_days')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('behaviour_categories');
            $table->smallInteger('repeat_count')->nullable();
            $table->foreignId('suggested_sanction_id')->constrained('sanction_types');
            $table->foreignId('notify_role_id')->nullable()->constrained('roles');
            $table->boolean('is_automatic')->default(false);
            $table->boolean('is_active')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('behaviour_trigger_rules');
    }
};

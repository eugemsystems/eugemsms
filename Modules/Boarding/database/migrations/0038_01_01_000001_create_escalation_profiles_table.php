<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book F BRD-02 §2/§3 ⭐ — the missing-learner ladder's own
 * configuration. Created before `roll_call_points` in execution
 * order despite being listed after it in the spec, since
 * `roll_call_points.escalation_profile_id` references it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('escalation_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('description', 255)->nullable();
            $table->boolean('is_default')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('escalation_profiles');
    }
};

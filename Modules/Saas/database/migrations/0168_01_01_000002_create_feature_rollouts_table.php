<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book J SAA-02 §2/BR-SAA-02-004 — operates `CORE-04`'s
 * `feature_flags`/`feature_flag_overrides` cross-tenant; this table
 * never stores rollout state itself, only the STAGE bookkeeping (see
 * `AdvanceFeatureRolloutStageAction`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_rollouts', function (Blueprint $table): void {
            $table->id();
            $table->string('feature_flag_key', 80);
            $table->foreign('feature_flag_key')->references('key')->on('feature_flags')->cascadeOnDelete();
            $table->string('rollout_stage', 20);
            $table->json('pilot_tenant_ids')->nullable();
            $table->unsignedTinyInteger('percentage')->nullable();
            $table->timestamp('started_at');
            $table->foreignId('started_by')->constrained('users');
            $table->string('notes', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_rollouts');
    }
};

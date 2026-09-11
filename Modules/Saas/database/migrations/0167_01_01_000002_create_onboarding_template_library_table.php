<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book J SAA-03 §2/BR-SAA-03-002 — built on `CORE-04`'s
 * `configuration_profiles`; this module never stores a second copy of
 * school configuration, only a curated name/suitability tag over the
 * same profile.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_template_library', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('configuration_profile_id')->constrained('configuration_profiles')->cascadeOnDelete();
            $table->string('template_name', 150);
            $table->string('suited_for', 60)->nullable();
            $table->integer('used_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_template_library');
    }
};

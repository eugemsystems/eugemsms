<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book G BRD-07 §2/§3 ⭐ — `is_safeguarding_trigger` routes a record to
 * `BRD-08` and pauses the disciplinary process (BR-BRD-07-018). The
 * seeded trigger set is documented as never fully emptiable
 * (§3's own rule); this pass enforces that at the Action layer,
 * not with a DB constraint that can't express "at least one active".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('behaviour_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 120);
            $table->string('polarity', 10);
            $table->smallInteger('default_points');
            $table->tinyInteger('severity_level')->nullable();
            $table->boolean('requires_evidence')->default(false);
            $table->boolean('requires_head_review')->default(false);
            $table->boolean('auto_notify_guardian')->default(false);
            $table->foreignId('suggests_sanction_id')->nullable()->constrained('sanction_types');
            $table->boolean('is_safeguarding_trigger')->default(false);
            $table->smallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->unique(['school_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('behaviour_categories');
    }
};

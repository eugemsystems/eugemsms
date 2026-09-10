<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book F BRD-03 §2. `approval_chain_id` is a forward reference to
 * `CORE-07` (no FK yet — this codebase's CORE-07 wiring is deferred
 * everywhere it's mentioned, matching `AmendMarkAction`'s own
 * documented boundary).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exeat_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 120);
            $table->smallInteger('max_duration_hours')->nullable();
            $table->boolean('requires_guardian_request')->default(true);
            $table->boolean('requires_document')->default(false);
            $table->unsignedBigInteger('approval_chain_id')->nullable();
            $table->smallInteger('min_notice_hours')->default(24);
            $table->smallInteger('allowed_per_term')->nullable();
            $table->boolean('counts_toward_quota')->default(true);
            $table->boolean('blocks_on_fee_arrears')->default(false);
            $table->boolean('blocks_on_suspension')->default(true);
            $table->boolean('is_active')->default(true);

            $table->unique(['school_id', 'code'], 'exeat_types_code_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exeat_types');
    }
};

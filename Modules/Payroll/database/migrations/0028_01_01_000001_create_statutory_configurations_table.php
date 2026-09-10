<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H3 PPL-05 §2/§0.1 ⭐ — every statutory rate, band, and ceiling
 * lives here, versioned and effective-dated, never a constant.
 * `school_id` is deliberately nullable (a system-wide seeded default)
 * — this table is NOT `BelongsToSchool`, because that scope's global
 * filter would make every system-default row (`school_id IS NULL`)
 * invisible to every school. `StatutoryConfigResolver` does its own
 * explicit "school-specific row, else the system default" query.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('statutory_configurations', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('config_type', 40);
            $table->char('currency', 3)->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->json('configuration');
            $table->string('source_reference', 200)->nullable();
            $table->boolean('requires_confirmation')->default(false);
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->string('status', 20);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['school_id', 'config_type', 'currency', 'effective_from'], 'statutory_configs_lookup_idx');
            $table->index(['school_id', 'requires_confirmation', 'status'], 'statutory_configs_confirmation_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statutory_configurations');
    }
};

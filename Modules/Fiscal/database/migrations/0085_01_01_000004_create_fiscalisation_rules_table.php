<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H3 FIN-13 §3/BR-FIN-13-002/003 — the routing engine's own
 * configuration. Evaluated by `priority`; every rule carries a
 * `rationale` and an accountant `reviewed_by` sign-off before it may
 * activate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscalisation_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('rule_name', 150);
            $table->string('source_type', 60);
            $table->string('source_identifier', 60)->nullable();
            $table->boolean('is_fiscalisable');
            $table->string('tax_type', 20);
            $table->decimal('tax_rate_percent', 5, 2)->default(0);
            $table->string('tax_code', 20)->nullable();
            $table->string('rationale', 255)->nullable();
            $table->smallInteger('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();

            $table->index(['school_id', 'source_type', 'is_active', 'priority'], 'fiscalisation_rules_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscalisation_rules');
    }
};

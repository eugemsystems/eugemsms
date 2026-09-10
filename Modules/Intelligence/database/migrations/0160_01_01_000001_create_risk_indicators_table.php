<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book J INT-03 §2 ⭐/BR-INT-03-003. A materialization of
 * `Modules\Intelligence\Domain\Registry\RiskIndicatorRegistry` — the
 * same code-owns-the-list pattern as `report_fields`/`kpi_definitions`.
 * No `school_id`: a registered indicator is a system-wide fact, owned
 * by the module whose data it reads. A school's own per-indicator
 * weighting/enablement lives in `risk_score_weights`, not here.
 *
 * No `data_source_query` column — the spec's own sketch of it as a
 * stored string doesn't fit this codebase's actual resolver mechanism.
 * Every other code-owned registry in this book set (`WidgetDefinition`'s
 * `resolver`, `KpiDefinitionEntry`'s `valueResolver`) holds its data
 * access as a real PHP `Closure` registered in-process, not a
 * serialisable query string; `RiskIndicatorDefinition` follows the
 * same pattern for the same reason. The source module a factor traces
 * back to is still real and visible — it's `module_code` here, plus
 * the `source` string each computed factor states for itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_indicators', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 60)->unique();
            $table->string('module_code', 20);
            $table->string('applies_to', 20);
            $table->string('plain_language_description', 255);
            $table->decimal('default_weight', 5, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_indicators');
    }
};

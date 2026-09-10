<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Modules\Intelligence\Domain\Registry\RiskIndicatorRegistry;

/**
 * Materializes `RiskIndicatorRegistry` into `risk_indicators` — same
 * safe re-sync-from-migration pattern as `KpiRegistry::syncToDatabase()`.
 */
return new class extends Migration
{
    public function up(): void
    {
        RiskIndicatorRegistry::syncToDatabase();
    }

    public function down(): void
    {
        // Deliberately no-op — see Core's 0009_01_01_000010.
    }
};

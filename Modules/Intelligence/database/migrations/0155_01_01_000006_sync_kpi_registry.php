<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Modules\Intelligence\Domain\Registry\KpiRegistry;

/**
 * Materializes `KpiRegistry` into `kpi_definitions` — same safe
 * re-sync-from-migration pattern as `WidgetRegistry`; see Core's
 * `0009_01_01_000010_sync_core05_setting_definitions.php`.
 */
return new class extends Migration
{
    public function up(): void
    {
        KpiRegistry::syncToDatabase();
    }

    public function down(): void
    {
        // Deliberately no-op — see Core's 0009_01_01_000010.
    }
};

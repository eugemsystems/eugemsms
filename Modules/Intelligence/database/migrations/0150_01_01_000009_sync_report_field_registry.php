<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Modules\Intelligence\Domain\Registry\ReportFieldRegistry;

/**
 * Materializes `ReportFieldRegistry` into `report_fields`/`report_entities`
 * — same safe re-sync-from-migration pattern as `WidgetRegistry`; see
 * Core's `0009_01_01_000010_sync_core05_setting_definitions.php`.
 */
return new class extends Migration
{
    public function up(): void
    {
        ReportFieldRegistry::syncToDatabase();
    }

    public function down(): void
    {
        // Deliberately no-op — see Core's 0009_01_01_000010.
    }
};

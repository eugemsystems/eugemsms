<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Modules\Comms\Domain\Registry\CalendarSourceRegistry;

/**
 * Materializes `CalendarSourceRegistry` into `calendar_sources` — same
 * safe re-sync-from-migration pattern as `WidgetRegistry`/
 * `SettingDefinitionRegistry`; see Core's
 * `0009_01_01_000010_sync_core05_setting_definitions.php`.
 */
return new class extends Migration
{
    public function up(): void
    {
        CalendarSourceRegistry::syncToDatabase();
    }

    public function down(): void
    {
        // Deliberately no-op — see Core's 0009_01_01_000010.
    }
};

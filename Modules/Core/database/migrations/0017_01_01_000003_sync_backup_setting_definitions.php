<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Modules\Core\Domain\Registry\SettingDefinitionRegistry;

/**
 * See `0009_01_01_000010_sync_core05_setting_definitions.php` for why
 * re-syncing a code-owned registry from a migration is safe — this
 * picks up the four `backups.retention_*` settings CORE-13 adds.
 */
return new class extends Migration
{
    public function up(): void
    {
        SettingDefinitionRegistry::syncToDatabase();
    }

    public function down(): void
    {
        // Deliberately no-op — see 0009_01_01_000010.
    }
};

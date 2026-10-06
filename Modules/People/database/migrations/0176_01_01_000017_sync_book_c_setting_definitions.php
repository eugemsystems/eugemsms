<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Modules\Core\Domain\Registry\SettingDefinitionRegistry;

/**
 * See Core's `0009_01_01_000010_sync_core05_setting_definitions.php` for
 * why re-syncing a code-owned registry from a migration is safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        SettingDefinitionRegistry::syncToDatabase();
    }

    public function down(): void
    {
        // Deliberately no-op — see Core's 0009_01_01_000010.
    }
};

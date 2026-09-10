<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Modules\Core\Domain\Registry\SettingDefinitionRegistry;

/**
 * Book A CORE-04 §2 / CORE-09 §4. See
 * `0009_01_01_000010_sync_core05_setting_definitions.php` for why this
 * is safe to run again for each module's own new settings.
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

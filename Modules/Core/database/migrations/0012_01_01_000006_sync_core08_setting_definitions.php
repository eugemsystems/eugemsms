<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Modules\Core\Domain\Registry\SettingDefinitionRegistry;

/**
 * Book A CORE-04 §2 / CORE-08 §5. Re-syncs the full, merged
 * `SettingDefinitionRegistry` — see `0009_01_01_000010_sync_core05_
 * setting_definitions.php` for why this is safe to run again.
 */
return new class extends Migration
{
    public function up(): void
    {
        SettingDefinitionRegistry::syncToDatabase();
    }

    public function down(): void
    {
        // See 0009_01_01_000010: deliberately no-op.
    }
};

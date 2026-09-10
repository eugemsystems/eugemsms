<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Modules\Core\Domain\Registry\FileCategoryRegistry;
use Modules\Core\Domain\Registry\ImporterRegistry;

/**
 * See `0009_01_01_000010_sync_core05_setting_definitions.php` for why
 * re-syncing a code-owned registry from a migration is safe — this
 * also re-syncs `FileCategoryRegistry` to pick up the new
 * `import_correction_file` category CORE-11 adds.
 */
return new class extends Migration
{
    public function up(): void
    {
        ImporterRegistry::syncToDatabase();
        FileCategoryRegistry::syncToDatabase();
    }

    public function down(): void
    {
        // Deliberately no-op — see 0009_01_01_000010.
    }
};

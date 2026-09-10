<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Modules\Core\Domain\Registry\SettingDefinitionRegistry;

/**
 * Book A CORE-04 §2 / CORE-05 §10. By the time `migrate` runs this file,
 * every service provider has already booted and registered its setting
 * definitions into the in-memory `SettingDefinitionRegistry` — this
 * mirrors that full, merged list into `setting_definitions`. Safe to
 * run again for a later module's own settings: registration is
 * cumulative across every provider's boot(), so a later sync still
 * carries CORE-05's entries forward.
 */
return new class extends Migration
{
    public function up(): void
    {
        SettingDefinitionRegistry::syncToDatabase();
    }

    public function down(): void
    {
        // Deliberately no-op: reversing this would delete setting_definitions
        // rows that may by now hold real, user-set values elsewhere via
        // setting_values — the definitions table is a mirror of code, not
        // migration-owned data.
    }
};

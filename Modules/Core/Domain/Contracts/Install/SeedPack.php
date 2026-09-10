<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Contracts\Install;

use Modules\Core\Models\School;

/**
 * Book A CORE-01 §7 — one Zimbabwe baseline seed pack. Modules register
 * their own pack into `SeedPackRegistry` when they exist; a module that
 * doesn't exist yet is represented by `PendingSeedPack` so the installer
 * UI can show it without crashing.
 */
interface SeedPack
{
    public function code(): string;

    public function label(): string;

    public function description(): string;

    public function isAvailable(): bool;

    public function run(School $school): SeedPackOutcome;
}

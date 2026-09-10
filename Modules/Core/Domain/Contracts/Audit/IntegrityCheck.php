<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Contracts\Audit;

use Modules\Core\Domain\DataObjects\Audit\IntegrityCheckResult;

/**
 * Book A CORE-08 §4. One row of the integrity check suite table.
 */
interface IntegrityCheck
{
    public function checkType(): string;

    /**
     * False for a check whose owning module doesn't exist yet
     * (trial balance, cross-school FK, cached balance, orphan
     * records) — same deferred-availability shape as `SeedPack`.
     */
    public function isAvailable(): bool;

    public function run(?int $schoolId): IntegrityCheckResult;
}

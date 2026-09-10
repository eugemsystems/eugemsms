<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Contracts\Schools;

use Modules\Core\Models\School;

/**
 * Book A CORE-02 §4. BR-CORE-02-005: archiving is blocked while a school
 * has active learners or open financial periods — both concepts owned by
 * modules that don't exist yet (Book D People, Book B Finance).
 * BR-CORE-02-010: `base_currency` is immutable once any financial
 * transaction exists — also Book B. `NullSchoolLifecycleGuard` reports no
 * blockers and no financial activity until those books land and bind a
 * real implementation.
 */
interface SchoolLifecycleGuard
{
    /**
     * @return array<int, string> human-readable blocking reasons; empty means archiving is permitted
     */
    public function archiveBlockers(School $school): array;

    public function hasFinancialActivity(School $school): bool;
}

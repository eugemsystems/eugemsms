<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Schools;

use Modules\Core\Domain\Contracts\Schools\SchoolLifecycleGuard;
use Modules\Core\Models\School;

final class NullSchoolLifecycleGuard implements SchoolLifecycleGuard
{
    public function archiveBlockers(School $school): array
    {
        return [];
    }

    public function hasFinancialActivity(School $school): bool
    {
        return false;
    }
}

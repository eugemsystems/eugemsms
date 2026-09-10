<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events\Sessions;

use App\Models\User;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\Term;

final class SessionSwitched
{
    public function __construct(
        public readonly User $user,
        public readonly AcademicYear $academicYear,
        public readonly ?Term $term,
    ) {}
}

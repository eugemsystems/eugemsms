<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events\Schools;

use App\Models\User;
use Modules\Core\Models\School;

final class ActiveSchoolSwitched
{
    public function __construct(
        public readonly User $user,
        public readonly School $school,
    ) {}
}

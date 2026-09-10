<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events\Auth;

use App\Models\User;
use Carbon\CarbonInterface;

final class UserLockedOut
{
    public function __construct(
        public readonly User $user,
        public readonly CarbonInterface $lockedUntil,
    ) {}
}

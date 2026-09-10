<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Auth;

use Modules\Core\Domain\Contracts\Auth\BreachedPasswordChecker;

final class NullBreachedPasswordChecker implements BreachedPasswordChecker
{
    public function isBreached(string $password): bool
    {
        return false;
    }
}

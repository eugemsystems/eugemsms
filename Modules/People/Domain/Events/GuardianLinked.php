<?php

declare(strict_types=1);

namespace Modules\People\Domain\Events;

use Modules\People\Models\StudentGuardian;

final class GuardianLinked
{
    public function __construct(
        public readonly StudentGuardian $link,
    ) {}
}

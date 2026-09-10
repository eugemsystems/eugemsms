<?php

declare(strict_types=1);

namespace Modules\People\Domain\Events;

use Modules\People\Models\Guardian;

final class GuardianCreated
{
    public function __construct(
        public readonly Guardian $guardian,
    ) {}
}

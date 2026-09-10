<?php

declare(strict_types=1);

namespace Modules\People\Domain\Events;

use Modules\People\Models\Staff;

final class StaffCreated
{
    public function __construct(
        public readonly Staff $staff,
    ) {}
}

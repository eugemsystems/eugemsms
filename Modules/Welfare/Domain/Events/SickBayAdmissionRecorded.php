<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Events;

use Modules\Welfare\Models\SickBayAdmission;

final class SickBayAdmissionRecorded
{
    public function __construct(
        public readonly SickBayAdmission $admission,
    ) {}
}

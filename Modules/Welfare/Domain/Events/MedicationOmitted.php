<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Events;

use Modules\Welfare\Models\MedicationAdministration;

final class MedicationOmitted
{
    public function __construct(
        public readonly MedicationAdministration $administration,
    ) {}
}

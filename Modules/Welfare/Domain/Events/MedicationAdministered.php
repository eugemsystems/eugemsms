<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Events;

use Modules\Welfare\Models\MedicationAdministration;

final class MedicationAdministered
{
    public function __construct(
        public readonly MedicationAdministration $administration,
    ) {}
}

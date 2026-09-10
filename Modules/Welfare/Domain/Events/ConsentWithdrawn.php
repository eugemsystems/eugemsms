<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Events;

use Modules\Welfare\Models\MedicalConsent;

final class ConsentWithdrawn
{
    public function __construct(
        public readonly MedicalConsent $consent,
    ) {}
}

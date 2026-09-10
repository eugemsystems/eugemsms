<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\Events;

use Modules\Payroll\Models\StatutoryConfiguration;

final class StatutoryConfigActivated
{
    public function __construct(
        public readonly StatutoryConfiguration $configuration,
    ) {}
}

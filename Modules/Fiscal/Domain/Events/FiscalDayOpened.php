<?php

declare(strict_types=1);

namespace Modules\Fiscal\Domain\Events;

use Modules\Fiscal\Models\FiscalDay;

final class FiscalDayOpened
{
    public function __construct(
        public readonly FiscalDay $day,
    ) {}
}

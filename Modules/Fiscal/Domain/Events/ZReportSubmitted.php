<?php

declare(strict_types=1);

namespace Modules\Fiscal\Domain\Events;

use Modules\Fiscal\Models\FiscalZReport;

final class ZReportSubmitted
{
    public function __construct(
        public readonly FiscalZReport $zReport,
    ) {}
}

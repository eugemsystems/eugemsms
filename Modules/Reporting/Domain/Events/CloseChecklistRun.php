<?php

declare(strict_types=1);

namespace Modules\Reporting\Domain\Events;

use Modules\Reporting\Models\PeriodCloseChecklist;

final class CloseChecklistRun
{
    public function __construct(
        public readonly PeriodCloseChecklist $checklist,
    ) {}
}

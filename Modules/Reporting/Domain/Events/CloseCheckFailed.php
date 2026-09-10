<?php

declare(strict_types=1);

namespace Modules\Reporting\Domain\Events;

use Modules\Core\Domain\DataObjects\Sessions\ChecklistItemResult;
use Modules\Reporting\Models\PeriodCloseChecklist;

final class CloseCheckFailed
{
    public function __construct(
        public readonly PeriodCloseChecklist $checklist,
        public readonly ChecklistItemResult $failure,
    ) {}
}

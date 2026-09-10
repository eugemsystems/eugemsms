<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Sessions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Sessions\ChecklistResult;
use Modules\Core\Domain\Registry\CloseChecklistRegistry;
use Modules\Core\Domain\Support\PeriodType;
use Modules\Core\Models\Term;

/**
 * ACT-RunPeriodCloseChecklist (Book A CORE-03 §4). Read-only — runs every
 * registered validator for the given period type and reports the result;
 * never itself changes state (that's `ACT-TransitionPeriodState`, which
 * calls the same registry to enforce BR-CORE-03-013).
 */
final class RunPeriodCloseChecklistAction extends Action
{
    protected bool $transactional = false;

    public function execute(Term $term, PeriodType $type): ChecklistResult
    {
        $items = array_map(
            fn ($item) => $item->check($term),
            CloseChecklistRegistry::for($type),
        );

        return new ChecklistResult($items);
    }
}

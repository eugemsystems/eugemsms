<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Support;

use Modules\Welfare\Models\BehaviourRecord;

/**
 * Book G BRD-07 §3 ⭐ — the safeguarding routing rule. Bound by
 * default to `NullSafeguardingRouter` until `BRD-08` exists later in
 * this same book's build order; once it does, a real implementation
 * opens a `safeguarding_concerns`/`safeguarding_cases` row and returns
 * its case id, which `RecordBehaviourAction` stores on
 * `behaviour_records.safeguarding_case_id`.
 */
interface SafeguardingRouter
{
    /**
     * @return int|null the opened case id, or null if safeguarding is not wired yet
     */
    public function route(BehaviourRecord $record): ?int;
}

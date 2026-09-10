<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Support;

use Modules\Welfare\Models\BehaviourRecord;

/**
 * Book G BRD-07 §3 — the default binding until `BRD-08` exists. The
 * disciplinary pause (`is_confidential`/`status = 'under_review'`)
 * still happens for real in `RecordBehaviourAction` regardless of this
 * binding; only the actual case-opening is stubbed.
 */
final class NullSafeguardingRouter implements SafeguardingRouter
{
    public function route(BehaviourRecord $record): ?int
    {
        return null;
    }
}

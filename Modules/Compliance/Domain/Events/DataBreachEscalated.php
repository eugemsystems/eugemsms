<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\Events;

use Modules\Compliance\Models\DataBreach;

/**
 * Book H3 CMP-03 §3/BR-CMP-03-011 ⭐ (AC-CMP-03-004). Fired whenever a
 * breach involving minors' data is recorded — `RecordDataBreachAction`
 * itself already attempts the head/safeguarding-lead notification
 * synchronously; this event exists for anything else that should
 * react to an escalated breach later.
 */
final class DataBreachEscalated
{
    public function __construct(
        public readonly DataBreach $breach,
    ) {}
}

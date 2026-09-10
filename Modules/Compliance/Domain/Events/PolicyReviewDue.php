<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\Events;

use Modules\Compliance\Models\Policy;

/**
 * Book H3 CMP-04 §3/BR-CMP-04-004. Fires for every policy whose
 * `review_due_on` has passed — the owner and the head are the
 * intended recipients.
 */
final class PolicyReviewDue
{
    public function __construct(
        public readonly Policy $policy,
    ) {}
}

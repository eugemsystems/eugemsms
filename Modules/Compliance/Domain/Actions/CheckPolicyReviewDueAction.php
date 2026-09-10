<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Compliance\Domain\Events\PolicyReviewDue;
use Modules\Compliance\Models\Policy;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-CheckPolicyReviewDue (Book H3 CMP-04 §3/BR-CMP-04-004). A daily
 * scan, matching this book's own established "periodic scan, one
 * event per still-outstanding item" shape. No recipient-resolution
 * listener exists for "the owner and the head" yet — same documented
 * gap `RecordDataBreachAction`'s own docblock names for role-based
 * recipients (this one's "owner" isn't even a role, just whichever
 * user approved the policy, so there is even less to resolve against).
 */
final class CheckPolicyReviewDueAction extends Action
{
    /**
     * @return Collection<int, Policy>
     */
    public function execute(int $schoolId): Collection
    {
        $overdue = Policy::where('school_id', $schoolId)
            ->where('status', 'active')
            ->whereNotNull('review_due_on')
            ->whereDate('review_due_on', '<=', Carbon::now()->toDateString())
            ->get();

        foreach ($overdue as $policy) {
            event(new PolicyReviewDue($policy));
        }

        return $overdue;
    }
}

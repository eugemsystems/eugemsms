<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Actions;

use Illuminate\Support\Collection;
use Modules\Core\Domain\Actions\Action;
use Modules\Welfare\Models\VulnerableLearnerRegistration;

/**
 * ACT-ListOverdueVulnerableReviews (Book G BRD-08 §2/BR-BRD-08-018 —
 * "overdue reviews escalate").
 */
final class ListOverdueVulnerableReviewsAction extends Action
{
    /**
     * @return Collection<int, VulnerableLearnerRegistration>
     */
    public function execute(int $schoolId): Collection
    {
        return VulnerableLearnerRegistration::query()
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->whereDate('next_review_on', '<', now()->toDateString())
            ->get();
    }
}

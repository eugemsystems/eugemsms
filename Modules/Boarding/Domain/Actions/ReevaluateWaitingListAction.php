<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use Modules\Boarding\Models\HostelWaitingListEntry;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-ReevaluateWaitingList (Book F BRD-01 §4/BR-BRD-01-010). Recomputes
 * `position` by `priority_score` descending whenever a bed is
 * released. Offering the top entry a bed with an expiry
 * (`boarding.waitlist_offer_expiry_hours`) is deferred — no
 * notification infrastructure wired to this module yet; recomputing
 * position is the real, testable half this pass builds.
 */
final class ReevaluateWaitingListAction extends Action
{
    public function execute(int $schoolId, int $termId): void
    {
        $entries = HostelWaitingListEntry::query()
            ->where('school_id', $schoolId)
            ->where('term_id', $termId)
            ->where('status', 'waiting')
            ->orderByDesc('priority_score')
            ->orderBy('added_at')
            ->get();

        $this->transaction(function () use ($entries): void {
            foreach ($entries->values() as $index => $entry) {
                $entry->update(['position' => $index + 1]);
            }
        });
    }
}

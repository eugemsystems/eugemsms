<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Illuminate\Database\Eloquent\Collection;
use Modules\Comms\Models\ComplaintUpdate;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-GetComplaintThreadForRaiser (Book I COM-08 §4 ⭐/BR-COM-08-005
 * (AC-COM-08-003)). THE server-side boundary — every raiser-facing
 * API endpoint must call this, never `Complaint::updates()` directly,
 * or an internal note would leak through a naive `->with('updates')`.
 */
final class GetComplaintThreadForRaiserAction extends Action
{
    protected bool $transactional = false;

    /**
     * @return Collection<int, ComplaintUpdate>
     */
    public function execute(int $complaintId): Collection
    {
        return ComplaintUpdate::where('complaint_id', $complaintId)
            ->where('visible_to_raiser', true)
            ->orderBy('posted_at')
            ->get();
    }
}

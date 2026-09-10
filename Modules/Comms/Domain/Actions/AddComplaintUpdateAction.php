<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Comms\Models\Complaint;
use Modules\Comms\Models\ComplaintUpdate;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-AddComplaintUpdate (Book I COM-08 §3 ⭐/BR-COM-08-005
 * (AC-COM-08-003)). `visibleToRaiser = false` is how an assignee posts
 * an internal note — see `GetComplaintThreadForRaiserAction` for the
 * server-side boundary that keeps it from the raiser.
 */
final class AddComplaintUpdateAction extends Action
{
    public function execute(
        int $complaintId,
        string $updateType,
        int $postedByUserId,
        ?string $content = null,
        bool $visibleToRaiser = true,
    ): ComplaintUpdate {
        $complaint = Complaint::findOrFail($complaintId);

        return $this->transaction(fn (): ComplaintUpdate => ComplaintUpdate::create([
            'school_id' => $complaint->school_id,
            'complaint_id' => $complaint->id,
            'update_type' => $updateType,
            'content' => $content,
            'visible_to_raiser' => $visibleToRaiser,
            'posted_by' => $postedByUserId,
            'posted_at' => Carbon::now(),
        ]));
    }
}

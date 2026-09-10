<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\ApproveProjectBriefData;
use Modules\Academic\Models\ProjectBrief;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * ACT-ApproveProjectBrief (Book E ACA-06 §5/BR-ACA-06-008). HOD
 * sign-off — the only way a `draft` brief becomes eligible for
 * issue.
 */
final class ApproveProjectBriefAction extends Action
{
    public function execute(ApproveProjectBriefData $data): ProjectBrief
    {
        $brief = ProjectBrief::findOrFail($data->briefId);

        if ($brief->status !== 'draft') {
            throw new InvalidStateTransitionException(
                "A project brief must be draft to be approved (currently {$brief->status}).",
                ['brief_id' => $brief->id, 'status' => $brief->status],
            );
        }

        return $this->transaction(function () use ($brief, $data): ProjectBrief {
            $brief->update([
                'status' => 'approved',
                'approved_by' => $data->approvedByStaffId,
            ]);

            return $brief;
        });
    }
}

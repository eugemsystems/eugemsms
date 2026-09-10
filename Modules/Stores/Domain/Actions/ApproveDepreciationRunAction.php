<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Stores\Models\DepreciationRun;

final class ApproveDepreciationRunAction extends Action
{
    public function execute(int $runId, int $approvedByUserId): DepreciationRun
    {
        $run = DepreciationRun::findOrFail($runId);

        if ($run->status !== 'preview') {
            throw new InvalidStateTransitionException(
                "Depreciation run #{$run->id} must be in preview to approve (currently {$run->status}).",
                ['run_id' => $run->id, 'status' => $run->status],
            );
        }

        if ($run->computed_by === $approvedByUserId) {
            throw new InvalidStateTransitionException(
                'The user who computed a depreciation run cannot approve it themselves.',
                ['run_id' => $run->id],
            );
        }

        return $this->transaction(fn (): DepreciationRun => tap($run)->update([
            'status' => 'approved',
            'approved_by' => $approvedByUserId,
        ]));
    }
}

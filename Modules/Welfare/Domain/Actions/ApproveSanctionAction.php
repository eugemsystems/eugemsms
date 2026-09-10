<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Welfare\Domain\Events\SanctionActive;
use Modules\Welfare\Models\Sanction;

/**
 * ACT-ApproveSanction (Book G BRD-07 §4/BR-BRD-07-005/AC-BRD-07-002).
 */
final class ApproveSanctionAction extends Action
{
    public function execute(int $sanctionId, int $approvedByUserId): Sanction
    {
        $sanction = Sanction::findOrFail($sanctionId);

        if ($sanction->status !== 'pending_approval') {
            throw new InvalidStateTransitionException(
                "Sanction #{$sanction->id} must be pending_approval to approve (currently {$sanction->status}).",
                ['sanction_id' => $sanction->id, 'status' => $sanction->status],
            );
        }

        return $this->transaction(function () use ($sanction, $approvedByUserId): Sanction {
            $sanction->update([
                'status' => 'active',
                'approved_by' => $approvedByUserId,
            ]);

            event(new SanctionActive($sanction));

            return $sanction;
        });
    }
}

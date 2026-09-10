<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Welfare\Models\StudentLeadership;

/**
 * ACT-RevokeStudentLeadership (Book G BRD-07 §2/BR-BRD-07-020 —
 * "explicitly granted and revocable").
 */
final class RevokeStudentLeadershipAction extends Action
{
    public function execute(int $leadershipId, string $reason): StudentLeadership
    {
        $leadership = StudentLeadership::findOrFail($leadershipId);

        return $this->transaction(fn (): StudentLeadership => tap($leadership)->update([
            'status' => 'revoked',
            'ends_on' => now()->toDateString(),
            'revocation_reason' => $reason,
        ]));
    }
}

<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Welfare\Domain\Events\SanctionOverturned;
use Modules\Welfare\Models\Appeal;
use Modules\Welfare\Models\Sanction;

/**
 * ACT-DecideAppeal (Book G BRD-07 §2/BR-BRD-07-009 ⭐). An overturned
 * or reduced sanction is marked `overturned` — the row is never
 * deleted, so the record shows both the issue and the overturn
 * (AC-BRD-07-004).
 */
final class DecideAppealAction extends Action
{
    public function execute(int $appealId, string $outcome, string $outcomeReason, int $decidedByUserId, ?int $newSanctionId = null): Appeal
    {
        $appeal = Appeal::findOrFail($appealId);
        $sanction = Sanction::findOrFail($appeal->sanction_id);

        return $this->transaction(function () use ($appeal, $sanction, $outcome, $outcomeReason, $decidedByUserId, $newSanctionId): Appeal {
            $appeal->update([
                'outcome' => $outcome,
                'outcome_reason' => $outcomeReason,
                'new_sanction_id' => $newSanctionId,
                'decided_at' => Carbon::now(),
                'decided_by' => $decidedByUserId,
                'status' => 'decided',
            ]);

            if (in_array($outcome, ['overturned', 'reduced'], true)) {
                $sanction->update(['status' => 'overturned']);
                event(new SanctionOverturned($sanction));
            }

            return $appeal;
        });
    }
}

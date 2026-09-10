<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Academic\Domain\DataObjects\ApproveSpecialArrangementData;
use Modules\Academic\Models\SpecialArrangement;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * ACT-ApproveSpecialArrangement (Book E ACA-07 §4/BR-ACA-07-009/AC-ACA-07-006/007).
 */
final class ApproveSpecialArrangementAction extends Action
{
    public function execute(ApproveSpecialArrangementData $data): SpecialArrangement
    {
        $arrangement = SpecialArrangement::findOrFail($data->arrangementId);

        if ($arrangement->status !== 'requested') {
            throw new InvalidStateTransitionException(
                "Arrangement #{$arrangement->id} must be requested to be approved (currently {$arrangement->status}).",
                ['arrangement_id' => $arrangement->id, 'status' => $arrangement->status],
            );
        }

        return $this->transaction(fn (): SpecialArrangement => tap($arrangement)->update([
            'status' => 'approved',
            'approved_by' => $data->approvedByUserId,
            'approved_at' => Carbon::now(),
        ]));
    }
}

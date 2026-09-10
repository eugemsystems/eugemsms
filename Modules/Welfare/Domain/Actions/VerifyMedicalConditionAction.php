<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Welfare\Models\MedicalCondition;

/**
 * ACT-VerifyMedicalCondition (Book G BRD-06 §4/BR-BRD-06-005). A
 * guardian-declared condition already produces Tier 1/2 alerts before
 * this runs — verification narrows nothing, it only confirms.
 */
final class VerifyMedicalConditionAction extends Action
{
    public function execute(int $conditionId, int $verifiedByUserId): MedicalCondition
    {
        $condition = MedicalCondition::findOrFail($conditionId);

        return $this->transaction(fn (): MedicalCondition => tap($condition)->update([
            'verified_by_nurse' => true,
            'verified_at' => Carbon::now(),
            'verified_by' => $verifiedByUserId,
        ]));
    }
}

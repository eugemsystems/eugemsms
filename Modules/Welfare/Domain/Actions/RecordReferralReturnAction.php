<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Welfare\Models\ExternalReferral;

/**
 * ACT-RecordReferralReturn (Book G BRD-06 §2/BR-BRD-06-017). Moving
 * status out of `ExternalReferral::CURRENTLY_AWAY_STATUSES` is what
 * ends the `hospital` roll status for the next roll call.
 */
final class RecordReferralReturnAction extends Action
{
    public function execute(int $referralId, ?string $outcome = null): ExternalReferral
    {
        $referral = ExternalReferral::findOrFail($referralId);

        return $this->transaction(fn (): ExternalReferral => tap($referral)->update([
            'returned_at' => Carbon::now(),
            'outcome' => $outcome,
            'status' => 'returned',
        ]));
    }
}

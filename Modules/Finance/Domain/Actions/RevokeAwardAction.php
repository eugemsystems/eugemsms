<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\ReasonRequiredException;
use Modules\Finance\Domain\Events\AwardRevoked;
use Modules\Finance\Models\DiscountAward;

/**
 * ACT-RevokeAward (Book K FIN-07 §4/BR-FIN-07-012 (AC-FIN-07-007)).
 * Never retroactive — `effective_to` is set from the given date
 * forward; already-billed terms (and their `award_utilisation` rows)
 * are untouched.
 */
final class RevokeAwardAction extends Action
{
    public function execute(int $awardId, string $reason, ?CarbonInterface $effectiveTo = null): DiscountAward
    {
        if (trim($reason) === '') {
            throw new ReasonRequiredException('Revoking an award requires a reason (BR-FIN-07-012).');
        }

        $award = DiscountAward::query()->findOrFail($awardId);

        return $this->transaction(function () use ($award, $reason, $effectiveTo): DiscountAward {
            $award->update([
                'status' => 'revoked',
                'revoked_reason' => $reason,
                'effective_to' => ($effectiveTo ?? Carbon::today())->toDateString(),
            ]);

            $fresh = $award->fresh();
            event(new AwardRevoked($fresh));

            return $fresh;
        });
    }
}

<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Models\DiscountAward;

/**
 * ACT-ReinstateAward (Book K FIN-07 §4/BR-FIN-07-011). A failed condition
 * only suspends an award pending human review; this is the other outcome of
 * that review — reinstating it, with a recorded reason, rather than
 * revoking. Only a suspended award can be reinstated.
 */
final class ReinstateAwardAction extends Action
{
    public function execute(int $awardId, string $reason): DiscountAward
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Reinstating an award needs a recorded reason.');
        }

        $award = DiscountAward::query()->findOrFail($awardId);

        if ($award->status !== 'suspended') {
            throw new InvalidArgumentException("A {$award->status} award cannot be reinstated.");
        }

        return $this->transaction(function () use ($award, $reason): DiscountAward {
            $award->update(['status' => 'active', 'condition_met' => true, 'condition_note' => mb_substr(trim($award->condition_note.' | Reinstated: '.trim($reason), ' |'), 0, 255)]);

            return $award->fresh();
        });
    }
}

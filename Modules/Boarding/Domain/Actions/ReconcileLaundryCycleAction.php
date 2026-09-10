<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use Modules\Boarding\Domain\DataObjects\ReconcileLaundryCycleData;
use Modules\Boarding\Models\LaundryCycle;
use Modules\Boarding\Models\LaundryItem;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * ACT-ReconcileLaundryCycle (Book F BRD-05 §3/BR-BRD-05-005/007).
 * Refuses to reconcile while any learner's discrepancy is still open —
 * every shortfall must already be resolved or charged. Cost is tracked
 * per hostel per term for the departmental cost centre.
 */
final class ReconcileLaundryCycleAction extends Action
{
    public function execute(ReconcileLaundryCycleData $data): LaundryCycle
    {
        $cycle = LaundryCycle::findOrFail($data->laundryCycleId);

        if ($cycle->status !== 'returned') {
            throw new InvalidStateTransitionException(
                "Laundry cycle #{$cycle->id} must be returned to reconcile (currently {$cycle->status}).",
                ['laundry_cycle_id' => $cycle->id, 'status' => $cycle->status],
            );
        }

        $unresolved = LaundryItem::query()
            ->where('cycle_id', $cycle->id)
            ->where('resolved', false)
            ->exists();

        if ($unresolved) {
            throw new InvalidStateTransitionException(
                "Laundry cycle #{$cycle->id} has unresolved discrepancies and cannot reconcile.",
                ['laundry_cycle_id' => $cycle->id],
            );
        }

        return $this->transaction(fn (): LaundryCycle => tap($cycle)->update([
            'status' => 'reconciled',
            'cost_minor' => $data->costMinor,
        ]));
    }
}

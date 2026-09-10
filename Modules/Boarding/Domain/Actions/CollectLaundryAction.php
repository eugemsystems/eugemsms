<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use Modules\Boarding\Domain\DataObjects\CollectLaundryData;
use Modules\Boarding\Models\LaundryCycle;
use Modules\Boarding\Models\LaundryItem;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * ACT-CollectLaundry (Book F BRD-05 §3/BR-BRD-05-005). Records what
 * went out to the laundry, per learner, for later reconciliation
 * against what comes back.
 */
final class CollectLaundryAction extends Action
{
    public function execute(CollectLaundryData $data): LaundryCycle
    {
        $cycle = LaundryCycle::findOrFail($data->laundryCycleId);

        if ($cycle->status !== 'scheduled') {
            throw new InvalidStateTransitionException(
                "Laundry cycle #{$cycle->id} must be scheduled to collect (currently {$cycle->status}).",
                ['laundry_cycle_id' => $cycle->id, 'status' => $cycle->status],
            );
        }

        return $this->transaction(function () use ($cycle, $data): LaundryCycle {
            $totalOut = 0;

            foreach ($data->items as $entry) {
                LaundryItem::create([
                    'school_id' => $cycle->school_id,
                    'cycle_id' => $cycle->id,
                    'student_id' => $entry['studentId'],
                    'items_out' => $entry['itemsOut'],
                ]);

                $totalOut += $entry['itemsOut'];
            }

            $cycle->update([
                'collected_at' => $data->collectedAt,
                'items_collected' => $totalOut,
                'status' => 'collected',
            ]);

            return $cycle;
        });
    }
}

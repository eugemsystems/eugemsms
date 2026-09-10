<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use Modules\Boarding\Domain\DataObjects\RecordLaundryReturnData;
use Modules\Boarding\Models\LaundryCycle;
use Modules\Boarding\Models\LaundryItem;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Notifications\DispatchNotificationAction;
use Modules\Core\Domain\DataObjects\Notifications\DispatchNotificationData;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Throwable;

/**
 * ACT-RecordLaundryReturn (Book F BRD-05 §3/BR-BRD-05-005/006).
 * Reconciles items out against items back per learner — a shortfall
 * is a flagged discrepancy that must be resolved or charged before the
 * cycle itself can reconcile (`ReconcileLaundryCycleAction`). A
 * learner's second-or-later discrepancy in the same term escalates to
 * the hostel's housemaster.
 */
final class RecordLaundryReturnAction extends Action
{
    public function __construct(
        private readonly DispatchNotificationAction $dispatchNotification,
    ) {}

    public function execute(RecordLaundryReturnData $data): LaundryCycle
    {
        $cycle = LaundryCycle::findOrFail($data->laundryCycleId);

        if ($cycle->status !== 'collected') {
            throw new InvalidStateTransitionException(
                "Laundry cycle #{$cycle->id} must be collected to record a return (currently {$cycle->status}).",
                ['laundry_cycle_id' => $cycle->id, 'status' => $cycle->status],
            );
        }

        return $this->transaction(function () use ($cycle, $data): LaundryCycle {
            $totalBack = 0;
            $totalMissing = 0;

            foreach ($data->items as $entry) {
                $laundryItem = LaundryItem::query()
                    ->where('cycle_id', $cycle->id)
                    ->where('student_id', $entry['studentId'])
                    ->firstOrFail();

                $shortfall = max(0, $laundryItem->items_out - $entry['itemsBack']);

                $laundryItem->update([
                    'items_back' => $entry['itemsBack'],
                    'missing_description' => $entry['missingDescription'],
                    'resolved' => $shortfall === 0,
                ]);

                $totalBack += $entry['itemsBack'];
                $totalMissing += $shortfall;

                if ($shortfall > 0) {
                    $this->escalateIfRepeated($cycle, $laundryItem);
                }
            }

            $cycle->update([
                'returned_at' => $data->returnedAt,
                'items_returned' => $totalBack,
                'items_missing' => $totalMissing,
                'status' => 'returned',
            ]);

            return $cycle;
        });
    }

    private function escalateIfRepeated(LaundryCycle $cycle, LaundryItem $laundryItem): void
    {
        $priorDiscrepancies = LaundryItem::query()
            ->where('school_id', $laundryItem->school_id)
            ->where('student_id', $laundryItem->student_id)
            ->where('id', '!=', $laundryItem->id)
            ->whereColumn('items_back', '<', 'items_out')
            ->whereHas('cycle', fn ($query) => $query->where('term_id', $cycle->term_id))
            ->count();

        if ($priorDiscrepancies < 1) {
            return;
        }

        $housemasterStaffId = $cycle->hostel?->housemaster_staff_id;

        if ($housemasterStaffId === null) {
            return;
        }

        $staff = $cycle->hostel->housemaster;

        if ($staff?->user_id === null) {
            return;
        }

        try {
            $this->dispatchNotification->execute(new DispatchNotificationData(
                schoolId: $cycle->school_id,
                notificationKey: 'boarding.laundry_repeated_missing',
                recipientType: 'staff',
                addresses: ['email' => (string) ($staff->work_email ?? $staff->personal_email)],
                context: ['student_id' => $laundryItem->student_id],
                recipientId: $staff->user_id,
                relatedType: 'laundry_item',
                relatedId: $laundryItem->id,
            ));
        } catch (Throwable) {
            // Escalation is best-effort — the discrepancy itself is
            // already durable on the laundry_items row.
        }
    }
}

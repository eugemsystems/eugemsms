<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use Modules\Boarding\Domain\DataObjects\ApproveIssuedItemChargeData;
use Modules\Boarding\Models\LearnerIssuedItem;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Notifications\DispatchNotificationAction;
use Modules\Core\Domain\DataObjects\Notifications\DispatchNotificationData;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Models\Term;
use Modules\Finance\Domain\Actions\CreateAdHocChargeAction;
use Modules\Finance\Domain\DataObjects\CreateAdHocChargeData;
use Modules\People\Models\Student;
use Modules\People\Models\StudentGuardian;
use Throwable;

/**
 * ACT-ApproveLostItemCharge (Book F BRD-05 §3/BR-BRD-05-004). Charges
 * replacement cost after approval and notifies the learner's primary
 * guardian — a notification-dispatch failure never blocks the charge,
 * which is already durable once written.
 */
final class ApproveLostItemChargeAction extends Action
{
    public function __construct(
        private readonly CreateAdHocChargeAction $createAdHocCharge,
        private readonly DispatchNotificationAction $dispatchNotification,
    ) {}

    public function execute(ApproveIssuedItemChargeData $data): LearnerIssuedItem
    {
        $issuedItem = LearnerIssuedItem::findOrFail($data->learnerIssuedItemId);

        if ($issuedItem->status !== 'lost' || $issuedItem->ad_hoc_charge_id !== null) {
            throw new InvalidStateTransitionException(
                "Item #{$issuedItem->id} must be lost and uncharged to approve a charge.",
                ['learner_issued_item_id' => $issuedItem->id, 'status' => $issuedItem->status],
            );
        }

        $amount = $data->chargeAmountMinor ?? $issuedItem->issuableItem->replacement_cost_minor;
        $term = Term::findOrFail($issuedItem->term_id);

        return $this->transaction(function () use ($issuedItem, $data, $amount, $term): LearnerIssuedItem {
            $charge = $this->createAdHocCharge->execute(new CreateAdHocChargeData(
                schoolId: $issuedItem->school_id,
                academicYearId: $term->academic_year_id,
                termId: $issuedItem->term_id,
                studentId: $issuedItem->student_id,
                componentId: $data->feeComponentId,
                description: "Lost item replacement: {$issuedItem->issuableItem->name}",
                unitRateMinor: (int) $amount,
                currency: $issuedItem->issuableItem->currency,
                raisedByUserId: $data->approvedByUserId,
                sourceType: 'learner_issued_item',
                sourceId: $issuedItem->id,
                approvedByUserId: $data->approvedByUserId,
            ));

            $issuedItem->update([
                'charge_minor' => (int) $amount,
                'ad_hoc_charge_id' => $charge->id,
            ]);

            $this->notifyLearnerAndGuardian($issuedItem);

            return $issuedItem;
        });
    }

    private function notifyLearnerAndGuardian(LearnerIssuedItem $issuedItem): void
    {
        $student = Student::find($issuedItem->student_id);

        if ($student === null) {
            return;
        }

        $link = StudentGuardian::query()
            ->where('student_id', $student->id)
            ->where('is_primary_contact', true)
            ->where('status', 'active')
            ->with('guardian')
            ->first();

        if ($link === null || $link->guardian === null) {
            return;
        }

        $guardian = $link->guardian;

        try {
            $this->dispatchNotification->execute(new DispatchNotificationData(
                schoolId: $issuedItem->school_id,
                notificationKey: 'boarding.linen_item_charged',
                recipientType: 'guardian',
                addresses: [
                    'sms' => (string) $guardian->primary_phone,
                    'email' => (string) $guardian->email,
                ],
                context: [
                    'student' => ['first_name' => $student->first_name, 'last_name' => $student->last_name],
                    'item' => ['name' => $issuedItem->issuableItem->name],
                    'amount_minor' => $issuedItem->charge_minor,
                ],
                recipientId: $guardian->id,
                relatedType: 'learner_issued_item',
                relatedId: $issuedItem->id,
            ));
        } catch (Throwable) {
            // A notification-dispatch failure never reverses an
            // already-durable charge.
        }
    }
}

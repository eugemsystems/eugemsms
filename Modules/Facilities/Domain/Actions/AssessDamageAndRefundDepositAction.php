<?php

declare(strict_types=1);

namespace Modules\Facilities\Domain\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Facilities\Domain\DataObjects\AssessDamageAndRefundDepositData;
use Modules\Facilities\Models\ResourceBooking;
use Modules\Finance\Domain\Actions\PostJournalAction;
use Modules\Finance\Domain\DataObjects\JournalLineData;
use Modules\Finance\Domain\DataObjects\PostJournalData;

/**
 * ACT-AssessDamageAndRefundDeposit (Book H2 OPS-05 §3 ⭐/BR-OPS-05-006/
 * AC-OPS-05-003). Damage found after an external hire deducts from
 * the deposit with a recorded assessment; the balance refunds. One
 * journal releases the full liability: `Dr Deposits Held / Cr Cash`
 * for the refund and `Cr Damage Recovery Income` for the deduction —
 * the deposit was never income until this moment decides how much of
 * it actually becomes some.
 */
final class AssessDamageAndRefundDepositAction extends Action
{
    public function __construct(
        private readonly PostJournalAction $postJournal,
    ) {}

    public function execute(int $bookingId, AssessDamageAndRefundDepositData $data): ResourceBooking
    {
        $booking = ResourceBooking::findOrFail($bookingId);

        if ($booking->status !== 'completed') {
            throw new InvalidStateTransitionException(
                "Booking #{$booking->id} must be completed before its deposit can be assessed and refunded (currently {$booking->status}).",
                ['booking_id' => $booking->id, 'status' => $booking->status],
            );
        }

        if ($booking->deposit_amount_minor === null || $booking->deposit_amount_minor <= 0) {
            throw new InvalidStateTransitionException(
                "Booking #{$booking->id} has no deposit on file to refund.",
                ['booking_id' => $booking->id],
            );
        }

        if ($data->damageDeductedMinor > $booking->deposit_amount_minor) {
            throw ValidationException::withMessages([
                'damageDeductedMinor' => 'Damage deducted cannot exceed the deposit held.',
            ]);
        }

        if ($data->damageDeductedMinor > 0 && ($data->damageRecoveryIncomeAccountId === null || trim((string) $data->damageAssessmentNote) === '')) {
            throw ValidationException::withMessages([
                'damageAssessmentNote' => 'A recorded assessment is required before any amount is deducted from the deposit (BR-OPS-05-006).',
            ]);
        }

        $refundMinor = $booking->deposit_amount_minor - $data->damageDeductedMinor;
        $currency = Currency::from($data->currency);

        return $this->transaction(function () use ($booking, $data, $refundMinor, $currency): ResourceBooking {
            $lines = [
                new JournalLineData(accountId: $data->depositsHeldLiabilityAccountId, direction: 'DR', amount: Money::of($booking->deposit_amount_minor, $currency)),
            ];

            if ($refundMinor > 0) {
                $lines[] = new JournalLineData(accountId: $data->cashAccountId, direction: 'CR', amount: Money::of($refundMinor, $currency));
            }

            if ($data->damageDeductedMinor > 0) {
                $lines[] = new JournalLineData(accountId: (int) $data->damageRecoveryIncomeAccountId, direction: 'CR', amount: Money::of($data->damageDeductedMinor, $currency));
            }

            $this->postJournal->execute(new PostJournalData(
                schoolId: $booking->school_id,
                academicYearId: $data->academicYearId,
                termId: $data->termId,
                journalType: 'HIRE_DEPOSIT_REFUND',
                narration: "Hire deposit refund — booking {$booking->booking_number}",
                lines: $lines,
                effectiveAt: $data->refundedAt ?? Carbon::now(),
                postedByUserId: $data->performedByUserId,
                sourceType: 'resource_booking',
                sourceId: $booking->id,
            ));

            $booking->update([
                'damage_deducted_minor' => $data->damageDeductedMinor,
                'deposit_refunded' => true,
                'condition_after_notes' => $data->damageAssessmentNote ?? $booking->condition_after_notes,
            ]);

            return $booking;
        });
    }
}

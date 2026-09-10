<?php

declare(strict_types=1);

namespace Modules\Facilities\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Facilities\Domain\DataObjects\RecordHireDepositData;
use Modules\Facilities\Models\ResourceBooking;
use Modules\Finance\Domain\Actions\PostJournalAction;
use Modules\Finance\Domain\DataObjects\JournalLineData;
use Modules\Finance\Domain\DataObjects\PostJournalData;

/**
 * ACT-RecordHireDeposit (Book H2 OPS-05 §3/BR-OPS-05-005/
 * AC-OPS-05-003). Posts `Dr Cash / Cr Deposits Held (liability)` —
 * never income, until the booking completes and condition is
 * verified. `Modules\Finance`'s own `CreateReceiptAction` (`FIN-04`)
 * routes a `studentId`-less receipt to unidentified-deposit suspense,
 * not a clean known-hirer deposit liability, so this posts its own
 * journal directly, the same boundary
 * `Modules\Farm\Domain\Actions\RecordFarmSaleAction` already
 * documents for a payer `FIN-04`/`FIN-03` don't fit either.
 */
final class RecordHireDepositAction extends Action
{
    public function __construct(
        private readonly PostJournalAction $postJournal,
    ) {}

    public function execute(int $bookingId, RecordHireDepositData $data): ResourceBooking
    {
        $booking = ResourceBooking::findOrFail($bookingId);
        $currency = Currency::from($data->currency);

        return $this->transaction(function () use ($booking, $data, $currency): ResourceBooking {
            $this->postJournal->execute(new PostJournalData(
                schoolId: $booking->school_id,
                academicYearId: $data->academicYearId,
                termId: $data->termId,
                journalType: 'HIRE_DEPOSIT_RECEIVED',
                narration: "Hire deposit received — booking {$booking->booking_number}",
                lines: [
                    new JournalLineData(accountId: $data->cashAccountId, direction: 'DR', amount: Money::of($data->amountMinor, $currency)),
                    new JournalLineData(accountId: $data->depositsHeldLiabilityAccountId, direction: 'CR', amount: Money::of($data->amountMinor, $currency)),
                ],
                effectiveAt: $data->receivedAt ?? Carbon::now(),
                postedByUserId: $data->performedByUserId,
                sourceType: 'resource_booking',
                sourceId: $booking->id,
            ));

            $booking->update(['deposit_amount_minor' => $data->amountMinor]);

            return $booking;
        });
    }
}

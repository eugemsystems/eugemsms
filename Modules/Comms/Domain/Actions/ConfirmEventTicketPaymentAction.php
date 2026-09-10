<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Modules\Comms\Models\EventAttendee;
use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Models\Receipt;

/**
 * ACT-ConfirmEventTicketPayment (Book I COM-06 §3 ⭐/BR-COM-06-006
 * (AC-COM-06-003)).
 *
 * **The Book B gap this bridges honestly.** `BR-COM-06-006` asks for
 * "gated on payment" against a `FIN-02` ad hoc charge — but no code
 * anywhere in this codebase turns a `pending` `AdHocCharge` into an
 * `Invoice`, and no `Modules\Finance\Domain\Actions\CreateReceiptAction`
 * call can settle one directly (a receipt allocates against an OPEN
 * INVOICE, which a standalone ad hoc charge never has). Confirmed by
 * direct precedent: `Modules\Sport\Domain\Actions\JoinActivityAction`
 * (Book H2 OPS-07) raises the identical kind of charge and only ever
 * reaches `billing_status = 'charged'` — no module in this codebase
 * tracks "paid" for an ad hoc charge today.
 *
 * Rather than fabricate a Book B settlement pipeline this module has
 * no authority to build correctly, this action requires a REAL,
 * already-existing `Modules\Finance\Models\Receipt` id — issued
 * through whatever front-desk/cashier process the school uses today
 * for any other on-the-spot payment — and records it as the evidence
 * a human confirmed payment. `Modules\Comms\Domain\Actions\CheckInEventAttendeeAction`
 * gates on this action's own outcome (`status = 'paid'`), which is
 * fully real and testable even though the charge-to-receipt link
 * itself is manually asserted rather than system-derived.
 */
final class ConfirmEventTicketPaymentAction extends Action
{
    public function execute(int $attendeeId, int $receiptId): EventAttendee
    {
        return $this->transaction(function () use ($attendeeId, $receiptId): EventAttendee {
            $attendee = EventAttendee::findOrFail($attendeeId);
            $receipt = Receipt::where('school_id', $attendee->school_id)->findOrFail($receiptId);

            $attendee->update(['ticket_receipt_id' => $receipt->id, 'status' => 'paid']);

            return $attendee;
        });
    }
}

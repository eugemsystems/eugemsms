<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Domain\DataObjects\RequestFeeWaiverData;
use Modules\Finance\Models\FeeWaiver;

/**
 * ACT-RequestFeeWaiver (Book B FIN-03 §2/§5 `Finance\Waivers\Index`,
 * `finance.waiver.request`/`finance.write_off.request`). Only ever
 * lands `pending` — no journal, no invoice cache change. Nothing is
 * "real" until `ApproveFeeWaiverAction` runs, by a different user
 * (BR-FIN-03-012's CORE-07-style gate, the same two-person pattern
 * `journal.approve` uses).
 */
final class RequestFeeWaiverAction extends Action
{
    public function execute(RequestFeeWaiverData $data): FeeWaiver
    {
        return $this->transaction(fn (): FeeWaiver => FeeWaiver::create([
            'school_id' => $data->schoolId,
            'term_id' => $data->termId,
            'student_id' => $data->studentId,
            'invoice_id' => $data->invoiceId,
            'type' => $data->type,
            'amount_minor' => $data->amountMinor,
            'currency' => $data->currency,
            'reason_code' => $data->reasonCode,
            'reason' => $data->reason,
            'status' => 'pending',
            'requested_by' => $data->requestedByUserId,
        ]));
    }
}

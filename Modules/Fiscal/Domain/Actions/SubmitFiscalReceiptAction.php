<?php

declare(strict_types=1);

namespace Modules\Fiscal\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Fiscal\Domain\Contracts\FiscalGatewayDriver;
use Modules\Fiscal\Domain\Events\ReceiptFiscalised;
use Modules\Fiscal\Domain\Events\ReceiptRejected;
use Modules\Fiscal\Domain\Exceptions\FdmsUnreachableException;
use Modules\Fiscal\Models\FiscalDevice;
use Modules\Fiscal\Models\FiscalReceipt;

/**
 * ACT-SubmitFiscalReceipt (Book H3 FIN-13 §4 ⭐/BR-FIN-13-001/009/011/
 * 019). The one caller of `FiscalGatewayDriver::submitReceipt()` —
 * called synchronously from `RouteReceiptForFiscalisationAction` for
 * the common case, and again by `DrainOfflineFiscalQueueAction` for
 * retry. Never throws: `FdmsUnreachableException` moves the receipt
 * to `offline_queued`, a normal rejection moves it to `rejected` with
 * the full error retained (never discarded, BR-FIN-13-011) — both are
 * terminal-for-now outcomes this action reports by returning the
 * updated row, not by raising.
 */
final class SubmitFiscalReceiptAction extends Action
{
    public function __construct(
        private readonly FiscalGatewayDriver $driver,
    ) {}

    public function execute(int $fiscalReceiptId): FiscalReceipt
    {
        $fiscalReceipt = FiscalReceipt::findOrFail($fiscalReceiptId);

        if (in_array($fiscalReceipt->status, ['accepted'], true)) {
            return $fiscalReceipt;
        }

        $device = FiscalDevice::findOrFail($fiscalReceipt->device_id);

        $fiscalReceipt->update(['status' => 'submitting', 'attempt_count' => $fiscalReceipt->attempt_count + 1, 'last_attempt_at' => Carbon::now()]);

        try {
            $result = $this->driver->submitReceipt($device, $fiscalReceipt->payload);
        } catch (FdmsUnreachableException $e) {
            $fiscalReceipt->update([
                'status' => 'offline_queued',
                'error_code' => 'FDMS_UNREACHABLE',
                'error_message' => $e->getMessage(),
            ]);

            return $fiscalReceipt->fresh();
        }

        if (! $result->accepted) {
            $fiscalReceipt->update([
                'status' => 'rejected',
                'response' => $result->rawResponse,
                'error_code' => $result->errorCode,
                'error_message' => $result->errorMessage,
            ]);

            event(new ReceiptRejected($fiscalReceipt->fresh()));

            return $fiscalReceipt->fresh();
        }

        $fiscalReceipt->update([
            'status' => 'accepted',
            'submitted_at' => Carbon::now(),
            'accepted_at' => Carbon::now(),
            'fdms_receipt_id' => $result->fdmsReceiptId,
            'verification_code' => $result->verificationCode,
            'qr_url' => $result->qrUrl,
            'receipt_hash' => $result->receiptHash,
            'receipt_signature' => $result->receiptSignature,
            'response' => $result->rawResponse,
            'error_code' => null,
            'error_message' => null,
        ]);

        event(new ReceiptFiscalised($fiscalReceipt->fresh()));

        return $fiscalReceipt->fresh();
    }
}

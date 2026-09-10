<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Domain\DataObjects\CreateReceiptData;
use Modules\Finance\Domain\DataObjects\SettleGatewayPaymentData;
use Modules\Finance\Domain\Events\PaymentSettled;
use Modules\Finance\Models\PaymentGateway;
use Modules\Finance\Models\PaymentIntent;

/**
 * ACT-SettleGatewayPayment (Book B FIN-05 §7/BR-FIN-05-002/006/009/010
 * (AC-FIN-05-005/006)). The one place a confirmed settlement — from a
 * webhook or from polling — turns into a real receipt. Idempotent: an
 * already-`succeeded` intent is returned untouched, which is what
 * makes receiving the same settlement five times over produce exactly
 * one receipt (BR-FIN-05-006), whichever of webhook or poll got there
 * first.
 */
final class SettleGatewayPaymentAction extends Action
{
    public function __construct(
        private readonly CreateReceiptAction $createReceipt,
    ) {}

    public function execute(SettleGatewayPaymentData $data): PaymentIntent
    {
        $intent = PaymentIntent::findOrFail($data->paymentIntentId);

        if ($intent->status === 'succeeded') {
            return $intent;
        }

        $gateway = PaymentGateway::findOrFail($intent->gateway_id);

        return $this->transaction(function () use ($intent, $gateway, $data): PaymentIntent {
            $receipt = $this->createReceipt->execute(new CreateReceiptData(
                schoolId: $intent->school_id,
                academicYearId: $intent->academic_year_id,
                termId: $intent->term_id,
                receiptType: $intent->purpose === 'fees' ? 'fee' : 'sundry',
                payerType: $intent->student_id !== null ? 'guardian' : 'external',
                payerName: $intent->payer_name,
                currency: $intent->currency,
                tenders: [[
                    'tender_type' => $intent->method ?? 'gateway',
                    'amount_minor' => $data->amountMinor,
                    'currency' => $intent->currency,
                    'reference' => $data->gatewayReference,
                    'bank_account_id' => $gateway->settlement_account_id,
                ]],
                receivedByUserId: $data->processedByUserId,
                tillSessionId: null,
                studentId: $intent->student_id,
                payerPhone: $intent->payer_phone,
                narration: "Gateway settlement via {$gateway->name}",
                creditBalanceAccountId: $data->creditBalanceAccountId,
                suspenseAccountId: $data->suspenseAccountId,
                gatewayFeeMinor: $data->feeMinor,
                gatewayFeeExpenseAccountId: $data->feeMinor !== null ? $gateway->fee_account_id : null,
            ));

            $intent->update([
                'status' => 'succeeded',
                'gateway_reference' => $data->gatewayReference,
                'fee_minor' => $data->feeMinor,
                'net_settled_minor' => $data->amountMinor - ($data->feeMinor ?? 0),
                'receipt_id' => $receipt->id,
                'completed_at' => Carbon::now(),
            ]);

            event(new PaymentSettled($intent));

            return $intent;
        });
    }
}

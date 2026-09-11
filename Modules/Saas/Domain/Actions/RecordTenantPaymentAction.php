<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Saas\Domain\DataObjects\RecordTenantPaymentData;
use Modules\Saas\Models\TenantInvoice;
use Modules\Saas\Models\TenantPayment;

/**
 * ACT-RecordTenantPayment (Book J SAA-01 §2). Marks the invoice paid
 * once its payments cover the total — a partial payment leaves it
 * `issued`, exactly like `FIN-01` invoices leave a balance outstanding
 * rather than snapping straight to paid.
 */
final class RecordTenantPaymentAction extends Action
{
    public function execute(RecordTenantPaymentData $data): TenantPayment
    {
        $invoice = TenantInvoice::query()->findOrFail($data->invoiceId);

        return $this->transaction(function () use ($invoice, $data): TenantPayment {
            $payment = TenantPayment::create([
                'tenant_id' => $invoice->tenant_id,
                'invoice_id' => $invoice->id,
                'amount_minor' => $data->amountMinor,
                'currency' => $data->currency,
                'payment_method' => $data->paymentMethod,
                'gateway_reference' => $data->gatewayReference,
                'received_at' => $data->receivedAt ?? Carbon::now(),
            ]);

            if ($invoice->amountPaidMinor() >= $invoice->total_minor && $invoice->status !== 'paid') {
                $invoice->update(['status' => 'paid']);
            }

            return $payment;
        });
    }
}

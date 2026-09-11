<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Domain\Support\PaymentGatewayDriverRegistry;
use Modules\Saas\Domain\DataObjects\IngestTenantGatewayWebhookData;
use Modules\Saas\Domain\DataObjects\RecordTenantPaymentData;
use Modules\Saas\Domain\Exceptions\GatewayWebhookNotASettlementException;
use Modules\Saas\Domain\Exceptions\GatewayWebhookSignatureInvalidException;
use Modules\Saas\Models\TenantPayment;

/**
 * ACT-IngestTenantGatewayWebhook (Book J SAA-01 §4/BR-SAA-01-009 ⭐).
 * Collecting the vendor's own subscription payment through a gateway
 * reuses `FIN-05`'s driver contract for signature verification and
 * payload parsing entirely — no second HMAC check, no second webhook
 * parser is written here (see `PaymentGatewayDriverRegistry`).
 */
final class IngestTenantGatewayWebhookAction extends Action
{
    public function __construct(
        private readonly PaymentGatewayDriverRegistry $drivers,
        private readonly RecordTenantPaymentAction $recordPayment,
    ) {}

    public function execute(IngestTenantGatewayWebhookData $data): TenantPayment
    {
        $driver = $this->drivers->resolve($data->driverKey);

        if (! $driver->verifyWebhook($data->headers, $data->body)) {
            throw new GatewayWebhookSignatureInvalidException('The gateway webhook signature could not be verified.');
        }

        $event = $driver->parseWebhook($data->headers, $data->body);

        if (! $event->isSettlement()) {
            throw new GatewayWebhookNotASettlementException("Webhook event [{$event->eventType}] with status [{$event->status}] is not a settlement — no tenant payment to record.");
        }

        return $this->recordPayment->execute(new RecordTenantPaymentData(
            invoiceId: $data->invoiceId,
            amountMinor: $event->amountMinor,
            currency: $event->currency,
            paymentMethod: 'gateway',
            gatewayReference: $event->gatewayReference,
        ));
    }
}

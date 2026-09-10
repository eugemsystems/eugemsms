<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Support;

use InvalidArgumentException;
use Modules\Core\Domain\Support\Money;
use Modules\Finance\Domain\Contracts\PaymentGatewayDriver;
use Modules\Finance\Domain\DataObjects\Gateway\CheckoutResponse;
use Modules\Finance\Domain\DataObjects\Gateway\DisbursementRequest;
use Modules\Finance\Domain\DataObjects\Gateway\DisbursementResponse;
use Modules\Finance\Domain\DataObjects\Gateway\HealthStatus;
use Modules\Finance\Domain\DataObjects\Gateway\PaymentStatusResult;
use Modules\Finance\Domain\DataObjects\Gateway\PushResponse;
use Modules\Finance\Domain\DataObjects\Gateway\RefundResponse;
use Modules\Finance\Domain\DataObjects\Gateway\WebhookEvent;
use Modules\Finance\Models\PaymentIntent;

/**
 * The only `PaymentGatewayDriver` this pass registers — see
 * `PaymentGatewayDriverRegistry`'s docblock for why ContiPay/Pesepay/
 * Paynow are deferred rather than guessed at.
 *
 * Deterministic and inspectable rather than a mock: `poll()` reports
 * whatever `payment_intents.metadata['simulated_status']` already
 * holds (a test sets it directly, the same way a real gateway's
 * sandbox would let you force an outcome), and `verifyWebhook()`/
 * `parseWebhook()` read a plain JSON body shaped
 * `{gateway_reference, status, amount_minor, currency, fee_minor?,
 * signature}` — a body whose `signature` isn't the literal string
 * `valid` fails verification, exactly like a tampered real payload
 * would fail a real HMAC check.
 */
final class FakePaymentGatewayDriver implements PaymentGatewayDriver
{
    public function key(): string
    {
        return 'fake';
    }

    public function supportedMethods(): array
    {
        return ['ecocash', 'visa', 'zipit'];
    }

    public function supportedCurrencies(): array
    {
        return ['USD', 'ZWG'];
    }

    public function createCheckout(PaymentIntent $intent): CheckoutResponse
    {
        return new CheckoutResponse(
            checkoutUrl: "https://fake-gateway.test/checkout/{$intent->reference}",
            gatewayReference: "FAKE-{$intent->reference}",
        );
    }

    public function createPush(PaymentIntent $intent, string $phone, string $method): PushResponse
    {
        return new PushResponse(
            gatewayReference: "FAKE-{$intent->reference}",
            instructions: "Enter your PIN on {$phone} to approve {$method}.",
            pollUrl: "https://fake-gateway.test/status/{$intent->reference}",
        );
    }

    public function poll(PaymentIntent $intent): PaymentStatusResult
    {
        $status = $intent->metadata['simulated_status'] ?? 'pending';

        return new PaymentStatusResult(
            status: $status,
            feeMinor: $intent->metadata['simulated_fee_minor'] ?? null,
            gatewayReference: $intent->gateway_reference,
        );
    }

    public function verifyWebhook(array $headers, string $body): bool
    {
        $payload = json_decode($body, true);

        return is_array($payload) && ($payload['signature'] ?? null) === 'valid';
    }

    public function parseWebhook(array $headers, string $body): WebhookEvent
    {
        $payload = json_decode($body, true);

        if (! is_array($payload) || ! isset($payload['gateway_reference'], $payload['status'], $payload['amount_minor'], $payload['currency'])) {
            throw new InvalidArgumentException('Malformed fake gateway webhook payload.');
        }

        return new WebhookEvent(
            eventType: $payload['event_type'] ?? 'settlement',
            gatewayReference: $payload['gateway_reference'],
            status: $payload['status'],
            amountMinor: (int) $payload['amount_minor'],
            currency: $payload['currency'],
            feeMinor: isset($payload['fee_minor']) ? (int) $payload['fee_minor'] : null,
            failureCode: $payload['failure_code'] ?? null,
            failureMessage: $payload['failure_message'] ?? null,
        );
    }

    public function refund(PaymentIntent $intent, Money $amount): RefundResponse
    {
        return new RefundResponse(success: true, gatewayReference: "FAKE-REFUND-{$intent->reference}");
    }

    public function supportsDisbursement(): bool
    {
        return true;
    }

    public function disburse(DisbursementRequest $request): DisbursementResponse
    {
        return new DisbursementResponse(success: true, gatewayReference: 'FAKE-DISBURSEMENT-'.uniqid());
    }

    public function healthCheck(): HealthStatus
    {
        return new HealthStatus(status: 'up');
    }
}

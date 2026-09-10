<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Contracts;

use Modules\Core\Domain\Support\Money;
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
 * Book B FIN-05 §4 — the literal driver contract. `ContiPay`/`Pesepay`/
 * `Paynow`/`SmilePay` implementations need real sandbox credentials
 * this pass does not have and are not built — see
 * `FakePaymentGatewayDriver`, the only implementation registered,
 * and `PaymentGatewayDriverRegistry`'s docblock.
 */
interface PaymentGatewayDriver
{
    public function key(): string;

    /**
     * @return array<int, string>
     */
    public function supportedMethods(): array;

    /**
     * @return array<int, string>
     */
    public function supportedCurrencies(): array;

    public function createCheckout(PaymentIntent $intent): CheckoutResponse;

    public function createPush(PaymentIntent $intent, string $phone, string $method): PushResponse;

    public function poll(PaymentIntent $intent): PaymentStatusResult;

    /**
     * @param  array<string, mixed>  $headers
     */
    public function verifyWebhook(array $headers, string $body): bool;

    /**
     * @param  array<string, mixed>  $headers
     */
    public function parseWebhook(array $headers, string $body): WebhookEvent;

    public function refund(PaymentIntent $intent, Money $amount): RefundResponse;

    public function supportsDisbursement(): bool;

    public function disburse(DisbursementRequest $request): DisbursementResponse;

    public function healthCheck(): HealthStatus;
}

<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Modules\Core\Domain\Support\Currency;
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
use Modules\Finance\Domain\Exceptions\GatewayRequestFailedException;
use Modules\Finance\Models\PaymentGateway;
use Modules\Finance\Models\PaymentIntent;

/**
 * Pesepay (Zimbabwe) `PaymentGatewayDriver` — hosted checkout, EcoCash-style push, status polling
 * and result callbacks. Credentials come from the intent's own `payment_gateways.credentials`
 * (`{"integration_key": "...", "encryption_key": "..."}`, encrypted at rest) and fall back to
 * `config('services.pesepay')` for a school that has not set its own; a webhook has no intent yet,
 * so it always uses the configured pair. Money is sent and read as decimals only at this boundary;
 * everywhere inside the system it stays integer minor units.
 *
 * Pesepay owns the transaction reference: `gateway_reference` is the `referenceNumber` it returns,
 * and our own `PaymentIntent::reference` travels as `merchantReference`. Statuses are mapped to the
 * platform's four: `succeeded`, `failed`, `cancelled`, `pending`.
 *
 * Refunds and disbursements are not offered through this integration.
 */
final class PesepayGatewayDriver implements PaymentGatewayDriver
{
    /** `SUCCESS` is the only status Pesepay documents as "paid". */
    private const array SUCCESS = ['SUCCESS'];

    /** Terminal and unpaid (docs: Transaction statuses). REVERSED is a paid-then-refunded multi-leg EcoCash payment. */
    private const array FAILED = ['FAILED', 'TERMINATED', 'TIME_OUT', 'CLOSED', 'CLOSED_PERIOD_ELAPSED', 'INSUFFICIENT_FUNDS', 'ERROR', 'DECLINED', 'AUTHORIZATION_FAILED', 'SERVICE_UNAVAILABLE', 'REVERSED'];

    private const array CANCELLED = ['CANCELLED'];

    public function key(): string
    {
        return 'pesepay';
    }

    public function supportedMethods(): array
    {
        return array_keys((array) config('services.pesepay.method_codes', []));
    }

    public function supportedCurrencies(): array
    {
        return ['USD', 'ZWG'];
    }

    public function createCheckout(PaymentIntent $intent): CheckoutResponse
    {
        $response = $this->send($intent, 'v1/payments/initiate', $this->basePayload($intent));
        $reference = $this->referenceFrom($response);
        $url = $this->requireString($response, 'redirectUrl');

        $intent->forceFill(['poll_url' => $response['pollUrl'] ?? null])->save();

        return new CheckoutResponse(checkoutUrl: $url, gatewayReference: $reference);
    }

    public function createPush(PaymentIntent $intent, string $phone, string $method): PushResponse
    {
        $code = $this->methodCode($method, $intent->currency);

        $response = $this->send($intent, 'v2/payments/make-payment', $this->basePayload($intent) + [
            'paymentMethodCode' => $code,
            'customer' => ['phoneNumber' => $phone, 'email' => $intent->payer_email, 'name' => $intent->payer_name],
            'paymentMethodRequiredFields' => ['customerPhoneNumber' => $phone],
        ]);

        return new PushResponse(
            gatewayReference: $this->referenceFrom($response),
            instructions: "Approve the {$method} prompt on {$phone} to complete the payment.",
            pollUrl: isset($response['pollUrl']) ? (string) $response['pollUrl'] : null,
        );
    }

    public function poll(PaymentIntent $intent): PaymentStatusResult
    {
        if ($intent->gateway_reference === null) {
            return new PaymentStatusResult(status: 'pending');
        }

        $credentials = $this->credentialsFor($intent);

        try {
            $raw = $this->request($credentials)->get($this->url('v1/payments/check-payment'), ['referenceNumber' => $intent->gateway_reference]);
        } catch (ConnectionException) {
            throw new GatewayRequestFailedException('Pesepay could not be reached.');
        }

        $response = $this->decode($raw, $credentials);

        return $this->statusResult($response, $intent->gateway_reference);
    }

    /**
     * The result callback is plain JSON that carries the merchant's own integration key in its
     * `Authorization` header (Pesepay docs: Verifying callbacks). The key is compared in constant
     * time against the key of the gateway the referenced payment was made through.
     */
    public function verifyWebhook(array $headers, string $body): bool
    {
        $data = json_decode($body, true);
        $reference = is_array($data) ? ($data['referenceNumber'] ?? null) : null;

        if (! is_string($reference) || $reference === '') {
            return false;
        }

        $sent = $headers['authorization'] ?? $headers['Authorization'] ?? '';
        $sent = is_array($sent) ? (string) ($sent[0] ?? '') : (string) $sent;
        $expected = $this->credentials($this->gatewayForReference($reference))['integration_key'];

        return $expected !== '' && $sent !== '' && hash_equals($expected, $sent);
    }

    /**
     * The callback body proves nothing on its own, so the outcome is confirmed server-to-server
     * with Check Payment Status and that answer is what settles. If the confirmation cannot be made
     * right now the event is `pending`, and the scheduled poll settles the payment instead.
     */
    public function parseWebhook(array $headers, string $body): WebhookEvent
    {
        $data = json_decode($body, true);
        $reference = is_array($data) ? ($data['referenceNumber'] ?? null) : null;

        if (! is_string($reference) || $reference === '') {
            throw new InvalidArgumentException('The Pesepay result carries no reference number.');
        }

        $credentials = $this->credentials($this->gatewayForReference($reference));

        try {
            $confirmed = $this->decode(
                $this->request($credentials)->get($this->url('v1/payments/check-payment'), ['referenceNumber' => $reference]),
                $credentials,
            );
        } catch (ConnectionException|GatewayRequestFailedException) {
            return new WebhookEvent(eventType: 'settlement', gatewayReference: $reference, status: 'pending', amountMinor: 0, currency: 'USD');
        }

        $amount = (array) ($confirmed['amountDetails'] ?? []);
        $status = $this->mapStatus((string) ($confirmed['transactionStatus'] ?? ''));
        $currency = $this->fromPesepayCurrency((string) ($amount['currencyCode'] ?? 'USD'));

        return new WebhookEvent(
            eventType: 'settlement',
            gatewayReference: $reference,
            status: $status,
            amountMinor: $this->toMinor((string) ($amount['amount'] ?? '0'), $currency),
            currency: $currency,
            feeMinor: isset($amount['transactionServiceFee']) ? $this->toMinor((string) $amount['transactionServiceFee'], $currency) : null,
            failureCode: $status === 'failed' ? (string) ($confirmed['transactionStatusCode'] ?? 'FAILED') : null,
            failureMessage: $status === 'failed' ? (string) ($confirmed['transactionStatusDescription'] ?? 'The payment failed.') : null,
        );
    }

    public function refund(PaymentIntent $intent, Money $amount): RefundResponse
    {
        return new RefundResponse(success: false, failureMessage: 'Refunds are made from the Pesepay merchant dashboard.');
    }

    public function supportsDisbursement(): bool
    {
        return false;
    }

    public function disburse(DisbursementRequest $request): DisbursementResponse
    {
        return new DisbursementResponse(success: false, failureMessage: 'Pesepay disbursements are not supported.');
    }

    public function healthCheck(): HealthStatus
    {
        $credentials = $this->credentials(null);

        if ($credentials['integration_key'] === '' || strlen($credentials['encryption_key']) !== 32) {
            return new HealthStatus(status: 'down', message: 'Pesepay credentials are not configured.');
        }

        try {
            $this->request($credentials)->get($this->url('v1/payments/check-payment'), ['referenceNumber' => 'health-check'])->status();
        } catch (ConnectionException $e) {
            return new HealthStatus(status: 'down', message: 'Pesepay could not be reached.');
        }

        return new HealthStatus(status: 'up');
    }

    /**
     * @return array<string, mixed>
     */
    private function basePayload(PaymentIntent $intent): array
    {
        $currency = Currency::from($intent->currency);

        return [
            'amountDetails' => ['amount' => (float) Money::of($intent->amount_minor, $currency)->toDecimal(), 'currencyCode' => $intent->currency === 'ZWG' ? 'ZiG' : $intent->currency],
            'merchantReference' => $intent->reference,
            'reasonForPayment' => $intent->purpose === 'fees' ? 'School fees' : ucfirst($intent->purpose),
            'resultUrl' => (string) config('services.pesepay.result_url'),
            'returnUrl' => (string) config('services.pesepay.return_url'),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function send(PaymentIntent $intent, string $path, array $payload): array
    {
        $credentials = $this->credentialsFor($intent);

        $body = ['payload' => (new PesepayCrypto($credentials['encryption_key']))->encrypt($payload)];

        try {
            $response = $this->request($credentials)->post($this->url($path), $body);
        } catch (ConnectionException) {
            throw new GatewayRequestFailedException('Pesepay could not be reached.');
        }

        return $this->decode($response, $credentials);
    }

    /**
     * @param  array{integration_key: string, encryption_key: string}  $credentials
     */
    private function request(array $credentials): PendingRequest
    {
        return Http::withHeaders(['authorization' => $credentials['integration_key'], 'content-type' => 'application/json'])
            ->acceptJson()
            ->timeout(20);
    }

    /**
     * @param  array{integration_key: string, encryption_key: string}  $credentials
     * @return array<string, mixed>
     */
    private function decode(Response $response, array $credentials): array
    {
        $body = $response->json();

        if (! $response->successful() || ! is_array($body) || ! isset($body['payload'])) {
            throw new GatewayRequestFailedException('Pesepay refused the request (HTTP '.$response->status().').');
        }

        return (new PesepayCrypto($credentials['encryption_key']))->decrypt((string) $body['payload'])
            ?? throw new GatewayRequestFailedException('Pesepay returned a response this merchant could not open.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function statusResult(array $data, string $reference): PaymentStatusResult
    {
        $status = $this->mapStatus((string) ($data['transactionStatus'] ?? ''));
        $amount = (array) ($data['amountDetails'] ?? []);
        $currency = $this->fromPesepayCurrency((string) ($amount['currencyCode'] ?? 'USD'));

        return new PaymentStatusResult(
            status: $status,
            feeMinor: isset($amount['transactionServiceFee']) ? $this->toMinor((string) $amount['transactionServiceFee'], $currency) : null,
            failureCode: $status === 'failed' ? (string) ($data['transactionStatusCode'] ?? 'FAILED') : null,
            failureMessage: $status === 'failed' ? (string) ($data['transactionStatusDescription'] ?? 'The payment failed.') : null,
            gatewayReference: $reference,
        );
    }

    private function mapStatus(string $status): string
    {
        $status = strtoupper(trim($status));

        return match (true) {
            in_array($status, self::SUCCESS, true) => 'succeeded',
            in_array($status, self::FAILED, true) => 'failed',
            in_array($status, self::CANCELLED, true) => 'cancelled',
            default => 'pending',
        };
    }

    /**
     * Pesepay calls the Zimbabwe gold-backed currency `ZiG`; this system's code for it is `ZWG`.
     */
    private function fromPesepayCurrency(string $code): string
    {
        $code = strtoupper(trim($code));

        return $code === 'ZIG' ? 'ZWG' : $code;
    }

    private function gatewayForReference(string $reference): ?PaymentGateway
    {
        $gatewayId = PaymentIntent::withoutGlobalScopes()->where('gateway_reference', $reference)->value('gateway_id');

        return $gatewayId === null ? null : PaymentGateway::withoutGlobalScopes()->whereKey($gatewayId)->first();
    }

    private function methodCode(string $method, string $currency): string
    {
        $codes = (array) config('services.pesepay.method_codes', []);
        $code = $codes[strtolower($method)][strtoupper($currency)] ?? null;

        return is_string($code) && $code !== '' ? $code : throw new InvalidArgumentException("Pesepay has no payment method code for [{$method}] in {$currency}.");
    }

    /**
     * @return array{integration_key: string, encryption_key: string}
     */
    private function credentialsFor(PaymentIntent $intent): array
    {
        return $this->credentials(PaymentGateway::query()->find($intent->gateway_id));
    }

    /**
     * @return array{integration_key: string, encryption_key: string}
     */
    private function credentials(?PaymentGateway $gateway): array
    {
        $own = $gateway !== null && $gateway->credentials !== '' ? json_decode($gateway->credentials, true) : null;

        return [
            'integration_key' => (string) (is_array($own) && isset($own['integration_key']) ? $own['integration_key'] : config('services.pesepay.integration_key', '')),
            'encryption_key' => (string) (is_array($own) && isset($own['encryption_key']) ? $own['encryption_key'] : config('services.pesepay.encryption_key', '')),
        ];
    }

    private function url(string $path): string
    {
        return rtrim((string) config('services.pesepay.base_url'), '/').'/'.$path;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    /**
     * The sandbox answers a make-payment call with `referenceNumber: null` and carries the reference
     * only inside `pollUrl` (confirmed against api.test.sandbox.pesepay.com), so both are accepted.
     *
     * @param  array<string, mixed>  $data
     */
    private function referenceFrom(array $data): string
    {
        $reference = $data['referenceNumber'] ?? null;

        if (is_string($reference) && $reference !== '') {
            return $reference;
        }

        $query = [];
        parse_str((string) parse_url((string) ($data['pollUrl'] ?? ''), PHP_URL_QUERY), $query);
        $fromUrl = $query['referenceNumber'] ?? null;

        return is_string($fromUrl) && $fromUrl !== '' ? $fromUrl : throw new GatewayRequestFailedException("Pesepay's response had no [referenceNumber].");
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function requireString(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : throw new GatewayRequestFailedException("Pesepay's response had no [{$key}].");
    }

    private function toMinor(string $decimal, string $currency): int
    {
        return Money::fromDecimal($decimal === '' ? '0' : $decimal, Currency::tryFrom($currency) ?? Currency::USD)->minor;
    }
}

<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Finance\Domain\DataObjects\InitiatePaymentData;
use Modules\Finance\Domain\Events\PaymentIntentCreated;
use Modules\Finance\Domain\Support\PaymentGatewayDriverRegistry;
use Modules\Finance\Models\PaymentGateway;
use Modules\Finance\Models\PaymentIntent;

/**
 * ACT-InitiatePayment (Book B FIN-05 §7/BR-FIN-05-001 ⭐, AC-FIN-05-001).
 * Repeating a client's `idempotencyKey` always returns the original
 * intent — Zimbabwean connectivity guarantees double submissions, and
 * this is the whole of how that stays harmless.
 */
final class InitiatePaymentAction extends Action
{
    public function __construct(
        private readonly PaymentGatewayDriverRegistry $drivers,
        private readonly SettingResolver $settings,
    ) {}

    public function execute(InitiatePaymentData $data): PaymentIntent
    {
        $existing = PaymentIntent::query()->where('idempotency_key', $data->idempotencyKey)->first();

        if ($existing !== null) {
            return $existing;
        }

        $gateway = PaymentGateway::findOrFail($data->gatewayId);
        $driver = $this->drivers->resolve($gateway->driver);

        return $this->transaction(function () use ($data, $gateway, $driver): PaymentIntent {
            $ttlMinutes = (int) $this->settings->get('finance.payment_intent_ttl_minutes', new ScopeChain(schoolId: $data->schoolId));

            $intent = PaymentIntent::create([
                'school_id' => $data->schoolId,
                'academic_year_id' => $data->academicYearId,
                'term_id' => $data->termId,
                'gateway_id' => $gateway->id,
                'reference' => 'PI/'.now()->format('YmdHis').'/'.mb_substr($data->idempotencyKey, 0, 8),
                'idempotency_key' => $data->idempotencyKey,
                'student_id' => $data->studentId,
                'payer_user_id' => $data->payerUserId,
                'payer_name' => $data->payerName,
                'payer_phone' => $data->payerPhone,
                'payer_email' => $data->payerEmail,
                'purpose' => $data->purpose,
                'amount_minor' => $data->amountMinor,
                'currency' => $data->currency,
                'method' => $data->method,
                'status' => 'created',
                'initiated_at' => Carbon::now(),
                'expires_at' => Carbon::now()->addMinutes($ttlMinutes),
            ]);

            if ($data->method !== null && $data->payerPhone !== null) {
                $push = $driver->createPush($intent, $data->payerPhone, $data->method);

                $intent->update([
                    'status' => 'pending',
                    'gateway_reference' => $push->gatewayReference,
                    'instructions' => $push->instructions,
                    'poll_url' => $push->pollUrl,
                ]);
            } else {
                $checkout = $driver->createCheckout($intent);

                $intent->update([
                    'status' => 'pending',
                    'gateway_reference' => $checkout->gatewayReference,
                    'checkout_url' => $checkout->checkoutUrl,
                ]);
            }

            event(new PaymentIntentCreated($intent));

            return $intent;
        });
    }
}

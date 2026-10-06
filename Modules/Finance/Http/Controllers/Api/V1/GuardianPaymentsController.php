<?php

declare(strict_types=1);

namespace Modules\Finance\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Http\Support\ApiResponse;
use Modules\Finance\Domain\Actions\InitiatePaymentAction;
use Modules\Finance\Domain\Actions\PollPendingIntentAction;
use Modules\Finance\Domain\DataObjects\InitiatePaymentData;
use Modules\Finance\Domain\DataObjects\PollPendingIntentData;
use Modules\Finance\Models\PaymentGateway;
use Modules\Finance\Models\PaymentIntent;
use Modules\People\Domain\Support\LinkedLearners;

/**
 * `/api/v1/finance/payments` for guardians (Volume 1 §9.3). A parent starts a payment for a linked
 * learner and follows it to a receipt. The `Idempotency-Key` header (enforced by `serp.idempotent`)
 * is also the intent's own key, scoped to the user, so a double-submit over a flaky connection
 * returns the original intent instead of charging twice. Nothing here posts to the ledger: the
 * receipt and the parent's credit come from settlement, whether the gateway's callback or a poll
 * gets there first.
 */
final class GuardianPaymentsController
{
    public function methods(): JsonResponse
    {
        return ApiResponse::ok($this->gateways()->map(fn (PaymentGateway $gateway): array => [
            'gateway_id' => $gateway->id,
            'name' => $gateway->name,
            'is_default' => $gateway->is_default,
            'methods' => $gateway->supported_methods,
            'currencies' => $gateway->supported_currencies,
        ])->values()->all());
    }

    public function store(Request $request, LinkedLearners $linked, InitiatePaymentAction $initiate): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate([
            'student' => ['required', 'string', 'max:40'],
            'amount_minor' => ['required', 'integer', 'min:100', 'max:1000000000'],
            'currency' => ['required', 'string', 'size:3'],
            'method' => ['nullable', 'string', 'max:30', 'required_with:phone'],
            'phone' => ['nullable', 'string', 'max:20', 'required_with:method'],
        ]);

        $link = $linked->linkFor($user, $data['student']);
        abort_if($link === null, 404);

        $currency = strtoupper($data['currency']);
        abort_unless(Currency::tryFrom($currency) !== null, 422, 'That currency is not supported.');

        $method = isset($data['method']) ? strtolower($data['method']) : null;
        $gateway = $this->gateways()->first(fn (PaymentGateway $g): bool => in_array($currency, $g->supported_currencies, true)
            && ($method === null || in_array($method, $g->supported_methods, true)));
        abort_if($gateway === null, 422, 'No payment method is available for that currency right now.');

        $intent = $initiate->execute(new InitiatePaymentData(
            schoolId: $link->school_id,
            academicYearId: SessionContext::year()->id,
            termId: (int) SessionContext::termId(),
            gatewayId: $gateway->id,
            idempotencyKey: $this->intentKey($user, (string) $request->header('Idempotency-Key')),
            payerName: $user->name,
            purpose: 'fees',
            amountMinor: $data['amount_minor'],
            currency: $currency,
            studentId: $link->student_id,
            payerUserId: $user->id,
            payerPhone: $data['phone'] ?? null,
            payerEmail: $user->email,
            method: $method,
        ));

        return ApiResponse::ok($this->intent($intent), status: 201);
    }

    public function show(Request $request, string $payment, PollPendingIntentAction $poll): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $intent = PaymentIntent::query()->where('ulid', $payment)->where('payer_user_id', $user->id)->first();
        abort_if($intent === null, 404);

        if (in_array($intent->status, ['pending', 'created', 'expired'], true)) {
            $intent = $poll->execute(new PollPendingIntentData($intent->id, $user->id));
        }

        return ApiResponse::ok($this->intent($intent));
    }

    /**
     * @return Collection<int, PaymentGateway>
     */
    private function gateways(): Collection
    {
        return PaymentGateway::query()->where('is_active', true)->orderByDesc('is_default')->orderBy('priority')->get()
            ->filter(fn (PaymentGateway $gateway): bool => $gateway->isAvailable())->values();
    }

    /**
     * `payment_intents.idempotency_key` is a 36-character unique column, so the user-scoped key is
     * folded into a UUID-shaped digest.
     */
    private function intentKey(User $user, string $key): string
    {
        $hash = sha1($user->id.'|'.$key);

        return sprintf('%s-%s-%s-%s-%s', substr($hash, 0, 8), substr($hash, 8, 4), substr($hash, 12, 4), substr($hash, 16, 4), substr($hash, 20, 12));
    }

    /**
     * @return array<string, mixed>
     */
    private function intent(PaymentIntent $intent): array
    {
        return [
            'id' => $intent->ulid,
            'reference' => $intent->reference,
            'status' => $intent->status,
            'amount' => Money::of($intent->amount_minor, Currency::from($intent->currency))->jsonSerialize(),
            'method' => $intent->method,
            'checkout_url' => $intent->checkout_url,
            'instructions' => $intent->instructions,
            'failure_message' => $intent->failure_message,
            'receipt_issued' => $intent->receipt_id !== null,
            'expires_at' => $intent->expires_at->toIso8601ZuluString(),
        ];
    }
}

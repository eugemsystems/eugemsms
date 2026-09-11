<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Modules\Core\Domain\Contracts\Install\LicenceClient;
use Modules\Core\Domain\Contracts\Install\LicenceServerResponse;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolModule;
use Modules\Core\Models\Tenant;
use Modules\Saas\Domain\Actions\CancelSubscriptionAction;
use Modules\Saas\Domain\Actions\ChangeSubscriptionPlanAction;
use Modules\Saas\Domain\Actions\CreateSubscriptionAction;
use Modules\Saas\Domain\Actions\GetMySubscriptionAction;
use Modules\Saas\Domain\Actions\IngestTenantGatewayWebhookAction;
use Modules\Saas\Domain\Actions\IssueLicenceKeyAction;
use Modules\Saas\Domain\Actions\IssueTenantInvoiceAction;
use Modules\Saas\Domain\Actions\MarkSubscriptionPastDueAction;
use Modules\Saas\Domain\Actions\ReactivateSubscriptionAction;
use Modules\Saas\Domain\Actions\RecordTenantPaymentAction;
use Modules\Saas\Domain\Actions\RecordUsageMeterAction;
use Modules\Saas\Domain\Actions\RenewSubscriptionAction;
use Modules\Saas\Domain\Actions\SuspendSubscriptionAction;
use Modules\Saas\Domain\Actions\ValidateLicenceKeyAction;
use Modules\Saas\Domain\DataObjects\ChangeSubscriptionPlanData;
use Modules\Saas\Domain\DataObjects\CreateSubscriptionData;
use Modules\Saas\Domain\DataObjects\IngestTenantGatewayWebhookData;
use Modules\Saas\Domain\DataObjects\IssueLicenceKeyData;
use Modules\Saas\Domain\DataObjects\IssueTenantInvoiceData;
use Modules\Saas\Domain\DataObjects\RecordTenantPaymentData;
use Modules\Saas\Domain\DataObjects\RecordUsageData;
use Modules\Saas\Domain\Events\SubscriptionStatusChanged;
use Modules\Saas\Domain\Events\UsageSoftWarningCrossed;
use Modules\Saas\Domain\Exceptions\GatewayWebhookSignatureInvalidException;
use Modules\Saas\Domain\Exceptions\UsageLimitExceededException;
use Modules\Saas\Domain\Support\UsageLimitGuard;
use Modules\Saas\Models\Subscription;
use Modules\Saas\Models\SubscriptionChange;
use Modules\Saas\Models\SubscriptionPlan;
use Modules\Saas\Models\TenantInvoice;
use Modules\Saas\Models\UsageMeter;

it('opens a subscription and immediately entitles every covered school to the plan (BR-SAA-01-002)', function (): void {
    $tenant = Tenant::factory()->create(['status' => 'trial']);
    $schoolA = School::factory()->for($tenant)->create();
    $schoolB = School::factory()->for($tenant)->create();
    $plan = SubscriptionPlan::factory()->create(['included_modules' => ['FIN', 'PPL']]);

    $subscription = app(CreateSubscriptionAction::class)->execute(new CreateSubscriptionData(
        tenantId: $tenant->id,
        planId: $plan->id,
        coveredSchoolIds: [$schoolA->id, $schoolB->id],
        billingCurrency: 'USD',
        currentPeriodStart: Carbon::today()->startOfMonth(),
        currentPeriodEnd: Carbon::today()->endOfMonth(),
        status: 'trial',
    ));

    expect($subscription->status)->toBe('trial')
        ->and($tenant->fresh()->status)->toBe('trial');

    foreach ([$schoolA, $schoolB] as $school) {
        expect(SchoolModule::withoutGlobalScopes()->where('school_id', $school->id)->where('module_code', 'FIN')->first()?->isCurrentlyEnabled())->toBeTrue()
            ->and(SchoolModule::withoutGlobalScopes()->where('school_id', $school->id)->where('module_code', 'PPL')->first()?->isCurrentlyEnabled())->toBeTrue();
    }
});

it('applies an upgrade immediately with a prorated invoice and syncs school_modules (BR-SAA-01-006/AC-SAA-01-003)', function (): void {
    $tenant = Tenant::factory()->create();
    $school = School::factory()->for($tenant)->create();
    $foundation = SubscriptionPlan::factory()->create(['tier' => 'foundation', 'price_per_learner_minor' => 100, 'included_modules' => ['FIN']]);
    $professional = SubscriptionPlan::factory()->create(['tier' => 'professional', 'price_per_learner_minor' => 300, 'included_modules' => ['FIN', 'ACA']]);

    $subscription = Subscription::factory()->for($tenant)->create([
        'plan_id' => $foundation->id,
        'covered_school_ids' => [$school->id],
        'learner_count_at_billing' => 100,
        'current_period_start' => Carbon::today()->startOfMonth()->toDateString(),
        'current_period_end' => Carbon::today()->endOfMonth()->toDateString(),
    ]);

    $change = app(ChangeSubscriptionPlanAction::class)->execute(new ChangeSubscriptionPlanData(
        subscriptionId: $subscription->id,
        newPlanId: $professional->id,
    ));

    expect($change->change_type)->toBe('upgrade')
        ->and($change->proration_credit_minor)->not->toBeNull()
        ->and($subscription->fresh()->plan_id)->toBe($professional->id)
        ->and(SchoolModule::withoutGlobalScopes()->where('school_id', $school->id)->where('module_code', 'ACA')->first()?->isCurrentlyEnabled())->toBeTrue()
        ->and(TenantInvoice::where('subscription_id', $subscription->id)->exists())->toBeTrue();
});

it('defers a downgrade to the next renewal, leaving the plan and entitlements untouched now (BR-SAA-01-006)', function (): void {
    $tenant = Tenant::factory()->create();
    $school = School::factory()->for($tenant)->create();
    $professional = SubscriptionPlan::factory()->create(['tier' => 'professional', 'price_per_learner_minor' => 300, 'included_modules' => ['FIN', 'ACA']]);
    $foundation = SubscriptionPlan::factory()->create(['tier' => 'foundation', 'price_per_learner_minor' => 100, 'included_modules' => ['FIN']]);

    $subscription = Subscription::factory()->for($tenant)->create([
        'plan_id' => $professional->id,
        'covered_school_ids' => [$school->id],
        'learner_count_at_billing' => 100,
        'current_period_start' => Carbon::today()->startOfMonth()->toDateString(),
        'current_period_end' => Carbon::today()->endOfMonth()->toDateString(),
    ]);

    $change = app(ChangeSubscriptionPlanAction::class)->execute(new ChangeSubscriptionPlanData(
        subscriptionId: $subscription->id,
        newPlanId: $foundation->id,
    ));

    expect($change->change_type)->toBe('downgrade')
        ->and($change->proration_credit_minor)->toBeNull()
        ->and($subscription->fresh()->plan_id)->toBe($professional->id);
});

it('applies a pending downgrade and advances the period on renewal (BR-SAA-01-006)', function (): void {
    $tenant = Tenant::factory()->create();
    $school = School::factory()->for($tenant)->create();
    $professional = SubscriptionPlan::factory()->create(['tier' => 'professional', 'price_per_learner_minor' => 300, 'included_modules' => ['FIN', 'ACA']]);
    $foundation = SubscriptionPlan::factory()->create(['tier' => 'foundation', 'price_per_learner_minor' => 100, 'included_modules' => ['FIN']]);

    // The period ends TODAY — this is exactly the moment RenewSubscriptionAction
    // is meant to run for this subscription.
    $periodEnd = Carbon::today();

    $subscription = Subscription::factory()->for($tenant)->create([
        'plan_id' => $professional->id,
        'covered_school_ids' => [$school->id],
        'learner_count_at_billing' => 100,
        'current_period_start' => $periodEnd->copy()->subMonthNoOverflow()->addDay()->toDateString(),
        'current_period_end' => $periodEnd->toDateString(),
    ]);

    SubscriptionChange::factory()->for($subscription)->create([
        'change_type' => 'downgrade',
        'from_plan_id' => $professional->id,
        'to_plan_id' => $foundation->id,
        'effective_from' => $periodEnd->toDateString(),
    ]);

    // Seed the school's entitlements as they'd genuinely be under the
    // professional plan, so the renewal's disable step has something
    // real to prove it acted on.
    SchoolModule::factory()->for($school)->create(['module_code' => 'FIN', 'is_enabled' => true]);
    SchoolModule::factory()->for($school)->create(['module_code' => 'ACA', 'is_enabled' => true]);

    $renewed = app(RenewSubscriptionAction::class)->execute($subscription->id);

    expect($renewed->plan_id)->toBe($foundation->id)
        ->and($renewed->current_period_start->toDateString())->toBe($periodEnd->copy()->addDay()->toDateString())
        ->and(SchoolModule::withoutGlobalScopes()->where('school_id', $school->id)->where('module_code', 'ACA')->first()?->isCurrentlyEnabled())->toBeFalse();
});

it('transitions subscription lifecycle status and keeps Tenant::status in lockstep', function (): void {
    Event::fake([SubscriptionStatusChanged::class]);

    $tenant = Tenant::factory()->create(['status' => 'active']);
    $subscription = Subscription::factory()->for($tenant)->create(['status' => 'active']);

    app(MarkSubscriptionPastDueAction::class)->execute($subscription->id);
    expect($subscription->fresh()->status)->toBe('past_due')->and($tenant->fresh()->status)->toBe('past_due');

    app(SuspendSubscriptionAction::class)->execute($subscription->id);
    expect($subscription->fresh()->status)->toBe('suspended')->and($tenant->fresh()->status)->toBe('suspended');

    app(ReactivateSubscriptionAction::class)->execute($subscription->id);
    expect($subscription->fresh()->status)->toBe('active')->and($tenant->fresh()->status)->toBe('active');

    Event::assertDispatchedTimes(SubscriptionStatusChanged::class, 3);
});

it('retains read/export access after cancellation and records an append-only change (BR-SAA-01-008)', function (): void {
    $tenant = Tenant::factory()->create(['status' => 'active']);
    $subscription = Subscription::factory()->for($tenant)->create(['status' => 'active']);

    $cancelled = app(CancelSubscriptionAction::class)->execute($subscription->id, 'no longer needed');

    expect($cancelled->status)->toBe('cancelled')
        ->and($cancelled->cancellation_reason)->toBe('no longer needed')
        ->and(SubscriptionChange::where('subscription_id', $subscription->id)->where('change_type', 'cancelled')->exists())->toBeTrue();
});

it('refuses to update or delete an append-only subscription_changes row', function (): void {
    $change = SubscriptionChange::factory()->create();

    expect(fn () => $change->update(['reason' => 'edited']))->toThrow(InvalidStateTransitionException::class);
    expect(fn () => $change->delete())->toThrow(InvalidStateTransitionException::class);
});

it('sends a soft warning exactly once per period and flags a hard limit over the plan band (BR-SAA-01-003/004)', function (): void {
    Event::fake([UsageSoftWarningCrossed::class]);

    $tenant = Tenant::factory()->create();
    $plan = SubscriptionPlan::factory()->create(['learner_band_max' => 100]);
    $subscription = Subscription::factory()->for($tenant)->create(['plan_id' => $plan->id]);

    $meter = app(RecordUsageMeterAction::class)->execute(new RecordUsageData(tenantId: $tenant->id, metric: UsageMeter::METRIC_ACTIVE_LEARNERS, usageValue: 92));
    expect($meter->soft_warning_sent)->toBeTrue()->and($meter->hard_limit_reached)->toBeFalse();
    Event::assertDispatchedTimes(UsageSoftWarningCrossed::class, 1);

    app(RecordUsageMeterAction::class)->execute(new RecordUsageData(tenantId: $tenant->id, metric: UsageMeter::METRIC_ACTIVE_LEARNERS, usageValue: 95));
    Event::assertDispatchedTimes(UsageSoftWarningCrossed::class, 1);

    $overLimit = app(RecordUsageMeterAction::class)->execute(new RecordUsageData(tenantId: $tenant->id, metric: UsageMeter::METRIC_ACTIVE_LEARNERS, usageValue: 105));
    expect($overLimit->hard_limit_reached)->toBeTrue();
});

it('blocks only the specific over-limit action via UsageLimitGuard, and only while the limit is exceeded (AC-SAA-01-004)', function (): void {
    $tenant = Tenant::factory()->create();

    UsageLimitGuard::assertLearnerEnrolmentAllowed($tenant->id);

    UsageMeter::factory()->create([
        'tenant_id' => $tenant->id,
        'period_month' => Carbon::today()->format('Y-m'),
        'metric' => UsageMeter::METRIC_ACTIVE_LEARNERS,
        'hard_limit_reached' => true,
    ]);

    expect(fn () => UsageLimitGuard::assertLearnerEnrolmentAllowed($tenant->id))->toThrow(UsageLimitExceededException::class);
});

it('leaves an invoice issued on a partial payment and marks it paid once fully covered', function (): void {
    $subscription = Subscription::factory()->create();
    $invoice = app(IssueTenantInvoiceAction::class)->execute(new IssueTenantInvoiceData(subscriptionId: $subscription->id, periodMonth: Carbon::today()->format('Y-m')));

    app(RecordTenantPaymentAction::class)->execute(new RecordTenantPaymentData(
        invoiceId: $invoice->id, amountMinor: intdiv($invoice->total_minor, 2), currency: $invoice->currency, paymentMethod: 'manual',
    ));
    expect($invoice->fresh()->status)->toBe('issued');

    app(RecordTenantPaymentAction::class)->execute(new RecordTenantPaymentData(
        invoiceId: $invoice->id, amountMinor: $invoice->total_minor - intdiv($invoice->total_minor, 2), currency: $invoice->currency, paymentMethod: 'manual',
    ));
    expect($invoice->fresh()->status)->toBe('paid');
});

it('records a gateway payment through FIN-05s own driver contract and refuses an unverifiable signature (BR-SAA-01-009)', function (): void {
    $subscription = Subscription::factory()->create(['billing_currency' => 'USD']);
    $invoice = app(IssueTenantInvoiceAction::class)->execute(new IssueTenantInvoiceData(subscriptionId: $subscription->id, periodMonth: Carbon::today()->format('Y-m')));

    $validBody = json_encode([
        'gateway_reference' => 'FAKE-REF-1', 'status' => 'succeeded',
        'amount_minor' => $invoice->total_minor, 'currency' => 'USD', 'signature' => 'valid',
    ]);

    $payment = app(IngestTenantGatewayWebhookAction::class)->execute(new IngestTenantGatewayWebhookData(
        driverKey: 'fake', invoiceId: $invoice->id, headers: [], body: $validBody,
    ));

    expect($payment->payment_method)->toBe('gateway')
        ->and($payment->gateway_reference)->toBe('FAKE-REF-1')
        ->and($invoice->fresh()->status)->toBe('paid');

    $invalidBody = json_encode(['gateway_reference' => 'x', 'status' => 'settled', 'amount_minor' => 1, 'currency' => 'USD', 'signature' => 'tampered']);

    expect(fn () => app(IngestTenantGatewayWebhookAction::class)->execute(new IngestTenantGatewayWebhookData(
        driverKey: 'fake', invoiceId: $invoice->id, headers: [], body: $invalidBody,
    )))->toThrow(GatewayWebhookSignatureInvalidException::class);
});

it('validates a licence key online and falls back to the offline grace window when unreachable (BR-SAA-01-007)', function (): void {
    $subscription = Subscription::factory()->create();
    $key = app(IssueLicenceKeyAction::class)->execute(new IssueLicenceKeyData(
        tenantId: $subscription->tenant_id, subscriptionId: $subscription->id, offlineGraceDays: 14,
    ));

    app()->bind(LicenceClient::class, fn () => new class implements LicenceClient
    {
        public function activate(string $licenceKey, string $installationUuid): LicenceServerResponse
        {
            return new LicenceServerResponse(reachable: true, valid: true);
        }

        public function validate(string $licenceKey, string $installationUuid): LicenceServerResponse
        {
            return new LicenceServerResponse(reachable: true, valid: true, expiresAt: now()->addYear());
        }
    });

    $validated = app(ValidateLicenceKeyAction::class)->execute($key->id);
    expect($validated->status)->toBe('active')->and($validated->last_validated_at)->not->toBeNull();

    // Now the server goes unreachable, but the key was validated moments
    // ago — well inside its 14-day offline grace window.
    app()->bind(LicenceClient::class, fn () => new class implements LicenceClient
    {
        public function activate(string $licenceKey, string $installationUuid): LicenceServerResponse
        {
            return new LicenceServerResponse(reachable: false, valid: false);
        }

        public function validate(string $licenceKey, string $installationUuid): LicenceServerResponse
        {
            return new LicenceServerResponse(reachable: false, valid: false);
        }
    });

    $stillGraced = app(ValidateLicenceKeyAction::class)->execute($key->id);
    expect($stillGraced->status)->toBe('active');

    // Past the grace window entirely.
    $key->update(['last_validated_at' => Carbon::now()->subDays(20)]);
    $expired = app(ValidateLicenceKeyAction::class)->execute($key->id);
    expect($expired->status)->toBe('expired');
});

it('never resolves another tenants subscription (AC-SAA-01-006)', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $subscriptionA = Subscription::factory()->for($tenantA)->create();
    Subscription::factory()->for($tenantB)->create();

    $resolved = app(GetMySubscriptionAction::class)->execute($tenantA->id);

    expect($resolved?->id)->toBe($subscriptionA->id)
        ->and($resolved?->tenant_id)->toBe($tenantA->id)
        ->and($resolved?->tenant_id)->not->toBe($tenantB->id);
});

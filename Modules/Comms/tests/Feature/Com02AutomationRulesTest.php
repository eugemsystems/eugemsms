<?php

use App\Models\User;
use Illuminate\Support\Facades\Event;
use Modules\Comms\Domain\Actions\ActivateAutomationRuleAction;
use Modules\Comms\Domain\Actions\AddRuleTemplateVariantAction;
use Modules\Comms\Domain\Actions\CreateAutomationRuleAction;
use Modules\Comms\Domain\Actions\DeactivateAutomationRuleAction;
use Modules\Comms\Domain\Actions\EstimateAutomationRuleCostAction;
use Modules\Comms\Domain\Actions\HandleAutomationEventAction;
use Modules\Comms\Domain\Actions\PreviewAutomationRuleAction;
use Modules\Comms\Domain\Actions\RegisterMessageGatewayAction;
use Modules\Comms\Domain\Actions\RunScanRuleAction;
use Modules\Comms\Domain\DataObjects\AddRuleTemplateVariantData;
use Modules\Comms\Domain\DataObjects\CreateAutomationRuleData;
use Modules\Comms\Domain\DataObjects\RegisterMessageGatewayData;
use Modules\Comms\Domain\Events\RuleMatchedZeroRecords;
use Modules\Comms\Domain\Exceptions\CostEstimateNotReviewedException;
use Modules\Comms\Domain\Exceptions\InvalidRuleEventException;
use Modules\Comms\Domain\Exceptions\InvalidRuleFieldException;
use Modules\Comms\Models\RuleExecution;
use Modules\Core\Domain\Actions\Notifications\CreateNotificationTemplateAction;
use Modules\Core\Domain\DataObjects\Notifications\CreateNotificationTemplateData;
use Modules\Core\Domain\DataObjects\Notifications\NotificationKeyDefinition;
use Modules\Core\Domain\Registry\NotificationKeyRegistry;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\Notification;
use Modules\Core\Models\School;
use Modules\Finance\Models\Invoice;
use Modules\People\Models\Guardian;
use Modules\People\Models\Student;
use Modules\People\Models\StudentGuardian;
use Modules\Wallet\Domain\Events\WalletNegative;
use Modules\Wallet\Models\StudentWallet;

/**
 * @return array<string, mixed>
 */
function com02Fixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $user = User::factory()->create();

    NotificationKeyRegistry::clear();
    NotificationKeyRegistry::register(new NotificationKeyDefinition(
        key: 'fee.overdue.stage_2', variables: ['invoice.balance_minor'],
        defaultChannels: ['sms'], defaultAudience: 'guardian', isTransactional: true,
    ));
    NotificationKeyRegistry::register(new NotificationKeyDefinition(
        key: 'wallet.negative_balance', variables: ['wallet.balance_minor'],
        defaultChannels: ['sms'], defaultAudience: 'guardian', isTransactional: true, isUrgent: true,
    ));

    app(CreateNotificationTemplateAction::class)->execute(new CreateNotificationTemplateData(
        key: 'fee.overdue.stage_2', channel: 'sms', body: 'Balance due: {{ invoice.balance_minor }}',
    ));
    app(CreateNotificationTemplateAction::class)->execute(new CreateNotificationTemplateData(
        key: 'wallet.negative_balance', channel: 'sms', body: 'Wallet balance: {{ wallet.balance_minor }}',
    ));

    app(RegisterMessageGatewayAction::class)->execute(new RegisterMessageGatewayData(
        schoolId: $school->id, channel: 'sms', driver: 'bulksms_zw', name: 'Test SMS Gateway',
        credentials: 'test-key', createdByUserId: $user->id,
    ));

    return compact('school', 'user');
}

/**
 * @return array{invoice: Invoice, guardian: Guardian}
 */
function com02OverdueInvoice(array $f, int $daysOverdue = 30, int $balanceMinor = 10000): array
{
    $student = Student::factory()->for($f['school'])->create();
    $guardian = Guardian::factory()->for($f['school'])->create(['primary_phone' => '0771234567', 'email' => 'guardian@example.com']);
    StudentGuardian::factory()->for($f['school'])->create([
        'student_id' => $student->id, 'guardian_id' => $guardian->id, 'is_fee_responsible' => true, 'status' => 'active',
    ]);

    $invoice = Invoice::factory()->for($f['school'])->create([
        'student_id' => $student->id,
        'due_date' => now()->subDays($daysOverdue)->toDateString(),
        'balance_minor' => $balanceMinor,
        'status' => 'overdue',
    ]);

    return compact('invoice', 'guardian');
}

it('creates a scan rule and its conditions, rejecting an unexposed field (AC-COM-02-003)', function (): void {
    $f = com02Fixture();

    $rule = app(CreateAutomationRuleAction::class)->execute(new CreateAutomationRuleData(
        schoolId: $f['school']->id, name: 'Fee overdue — 30 day notice', notificationKey: 'fee.overdue.stage_2',
        triggerType: 'scheduled_scan', scanEntity: 'invoice',
        conditions: [
            ['group_id' => 1, 'field' => 'invoice.balance_minor', 'operator' => 'gt', 'value' => 0],
            ['group_id' => 1, 'field' => 'invoice.days_overdue', 'operator' => 'gte', 'value' => 30],
        ],
        createdByUserId: $f['user']->id, throttleKey: 'invoice.id', throttleWindowHours: 168,
    ));

    expect($rule->conditions)->toHaveCount(2);

    expect(fn () => app(CreateAutomationRuleAction::class)->execute(new CreateAutomationRuleData(
        schoolId: $f['school']->id, name: 'Bad rule', notificationKey: 'fee.overdue.stage_2',
        triggerType: 'scheduled_scan', scanEntity: 'invoice',
        conditions: [['group_id' => 1, 'field' => 'student.has_active_payment_plan', 'operator' => 'eq', 'value' => false]],
        createdByUserId: $f['user']->id,
    )))->toThrow(InvalidRuleFieldException::class);

    expect(fn () => app(CreateAutomationRuleAction::class)->execute(new CreateAutomationRuleData(
        schoolId: $f['school']->id, name: 'Bad event rule', notificationKey: 'fee.overdue.stage_2',
        triggerType: 'event', eventName: 'SomeUnregisteredEvent', conditions: [],
        createdByUserId: $f['user']->id,
    )))->toThrow(InvalidRuleEventException::class);
});

it('dispatches exactly once for a newly-overdue invoice and respects the throttle window on repeated scans (AC-COM-02-001/002, BR-COM-02-005)', function (): void {
    $f = com02Fixture();
    ['invoice' => $invoice] = com02OverdueInvoice($f);

    $rule = app(CreateAutomationRuleAction::class)->execute(new CreateAutomationRuleData(
        schoolId: $f['school']->id, name: 'Fee overdue — 30 day notice', notificationKey: 'fee.overdue.stage_2',
        triggerType: 'scheduled_scan', scanEntity: 'invoice',
        conditions: [
            ['group_id' => 1, 'field' => 'invoice.balance_minor', 'operator' => 'gt', 'value' => 0],
            ['group_id' => 1, 'field' => 'invoice.days_overdue', 'operator' => 'gte', 'value' => 30],
        ],
        createdByUserId: $f['user']->id, throttleKey: 'invoice.id', throttleWindowHours: 168,
    ));

    $first = app(RunScanRuleAction::class)->execute($rule->id);
    expect($first->notifications_dispatched)->toBe(1);

    $second = app(RunScanRuleAction::class)->execute($rule->id);
    expect($second->notifications_dispatched)->toBe(0)
        ->and($second->records_matched)->toBe(1);

    expect(RuleExecution::where('rule_id', $rule->id)->where('skip_reason', 'throttled')->exists())->toBeTrue();

    // The throttle window has elapsed — dispatches again.
    RuleExecution::query()->update(['executed_at' => now()->subDays(8)]);
    $third = app(RunScanRuleAction::class)->execute($rule->id);
    expect($third->notifications_dispatched)->toBe(1);
});

it('never dispatches in preview mode, showing exactly the matched population (AC-COM-02-004)', function (): void {
    $f = com02Fixture();
    com02OverdueInvoice($f);

    $rule = app(CreateAutomationRuleAction::class)->execute(new CreateAutomationRuleData(
        schoolId: $f['school']->id, name: 'Fee overdue — 30 day notice', notificationKey: 'fee.overdue.stage_2',
        triggerType: 'scheduled_scan', scanEntity: 'invoice',
        conditions: [['group_id' => 1, 'field' => 'invoice.days_overdue', 'operator' => 'gte', 'value' => 30]],
        createdByUserId: $f['user']->id,
    ));

    $preview = app(PreviewAutomationRuleAction::class)->execute($rule->id);

    expect($preview->recordsMatched)->toBe(1)
        ->and($preview->notificationsDispatched)->toBe(0)
        ->and($preview->previewMatches)->toHaveCount(1)
        ->and(Notification::count())->toBe(0)
        ->and(RuleExecution::where('rule_id', $rule->id)->count())->toBe(0);
});

it('refuses activation without a reviewed cost estimate (AC-COM-02-005, BR-COM-02-008)', function (): void {
    $f = com02Fixture();
    com02OverdueInvoice($f);

    $rule = app(CreateAutomationRuleAction::class)->execute(new CreateAutomationRuleData(
        schoolId: $f['school']->id, name: 'Fee overdue — 30 day notice', notificationKey: 'fee.overdue.stage_2',
        triggerType: 'scheduled_scan', scanEntity: 'invoice',
        conditions: [['group_id' => 1, 'field' => 'invoice.days_overdue', 'operator' => 'gte', 'value' => 30]],
        createdByUserId: $f['user']->id,
    ));

    expect(fn () => app(ActivateAutomationRuleAction::class)->execute($rule->id))
        ->toThrow(CostEstimateNotReviewedException::class);

    $estimated = app(EstimateAutomationRuleCostAction::class)->execute($rule->id);
    expect($estimated->estimated_monthly_cost_minor)->not->toBeNull();

    $activated = app(ActivateAutomationRuleAction::class)->execute($rule->id);
    expect($activated->is_active)->toBeTrue();
});

it('deterministically assigns the same variant to the same invoice across repeated scans (AC-COM-02-006, BR-COM-02-009/010)', function (): void {
    $f = com02Fixture();
    com02OverdueInvoice($f);

    $rule = app(CreateAutomationRuleAction::class)->execute(new CreateAutomationRuleData(
        schoolId: $f['school']->id, name: 'Fee overdue — 30 day notice', notificationKey: 'fee.overdue.stage_2',
        triggerType: 'scheduled_scan', scanEntity: 'invoice',
        conditions: [['group_id' => 1, 'field' => 'invoice.days_overdue', 'operator' => 'gte', 'value' => 30]],
        createdByUserId: $f['user']->id, throttleKey: 'invoice.id', throttleWindowHours: 1,
    ));
    app(AddRuleTemplateVariantAction::class)->execute(new AddRuleTemplateVariantData(
        ruleId: $rule->id, variantKey: 'A', templateKey: 'fee.overdue.stage_2', weightPercent: 50,
    ));
    app(AddRuleTemplateVariantAction::class)->execute(new AddRuleTemplateVariantData(
        ruleId: $rule->id, variantKey: 'B', templateKey: 'fee.overdue.stage_2', weightPercent: 50,
    ));

    app(RunScanRuleAction::class)->execute($rule->id);
    $firstVariant = RuleExecution::where('rule_id', $rule->id)->whereNotNull('variant_key')->value('variant_key');

    RuleExecution::query()->update(['executed_at' => now()->subDays(8)]);
    app(RunScanRuleAction::class)->execute($rule->id);
    $secondVariant = RuleExecution::where('rule_id', $rule->id)->whereNotNull('variant_key')->orderByDesc('id')->value('variant_key');

    expect($secondVariant)->toBe($firstVariant);

    $variant = $rule->variants()->where('variant_key', $firstVariant)->firstOrFail();
    expect($variant->sent_count)->toBeGreaterThanOrEqual(2);
});

it('alerts after three consecutive scans match nothing (AC-COM-02-007)', function (): void {
    Event::fake([RuleMatchedZeroRecords::class]);
    $f = com02Fixture();

    $rule = app(CreateAutomationRuleAction::class)->execute(new CreateAutomationRuleData(
        schoolId: $f['school']->id, name: 'Fee overdue — 30 day notice', notificationKey: 'fee.overdue.stage_2',
        triggerType: 'scheduled_scan', scanEntity: 'invoice',
        conditions: [['group_id' => 1, 'field' => 'invoice.days_overdue', 'operator' => 'gte', 'value' => 9999]],
        createdByUserId: $f['user']->id,
    ));

    app(RunScanRuleAction::class)->execute($rule->id);
    Event::assertNotDispatched(RuleMatchedZeroRecords::class);
    app(RunScanRuleAction::class)->execute($rule->id);
    app(RunScanRuleAction::class)->execute($rule->id);

    Event::assertDispatched(RuleMatchedZeroRecords::class);
});

it('stops future dispatch on deactivation without retracting messages already sent (BR-COM-02-011)', function (): void {
    $f = com02Fixture();
    com02OverdueInvoice($f);

    $rule = app(CreateAutomationRuleAction::class)->execute(new CreateAutomationRuleData(
        schoolId: $f['school']->id, name: 'Fee overdue — 30 day notice', notificationKey: 'fee.overdue.stage_2',
        triggerType: 'scheduled_scan', scanEntity: 'invoice',
        conditions: [['group_id' => 1, 'field' => 'invoice.days_overdue', 'operator' => 'gte', 'value' => 30]],
        createdByUserId: $f['user']->id, throttleKey: 'invoice.id', throttleWindowHours: 1,
    ));

    app(RunScanRuleAction::class)->execute($rule->id);
    $sentBefore = Notification::where('status', 'sent')->count();
    expect($sentBefore)->toBe(1);

    app(DeactivateAutomationRuleAction::class)->execute($rule->id);
    expect($rule->fresh()->is_active)->toBeFalse();
    expect(Notification::where('status', 'sent')->count())->toBe($sentBefore);
});

it('dispatches an event-triggered rule when its registered domain event fires (BR-COM-02-001/006)', function (): void {
    $f = com02Fixture();
    $student = Student::factory()->for($f['school'])->create();
    $guardian = Guardian::factory()->for($f['school'])->create(['primary_phone' => '0771234567', 'email' => 'g@example.com']);
    StudentGuardian::factory()->for($f['school'])->create([
        'student_id' => $student->id, 'guardian_id' => $guardian->id, 'is_primary_contact' => true, 'status' => 'active',
    ]);
    $wallet = StudentWallet::factory()->for($f['school'])->create(['student_id' => $student->id, 'balance_minor' => -500]);

    $rule = app(CreateAutomationRuleAction::class)->execute(new CreateAutomationRuleData(
        schoolId: $f['school']->id, name: 'Wallet negative alert', notificationKey: 'wallet.negative_balance',
        triggerType: 'event', eventName: 'WalletNegative',
        conditions: [['group_id' => 1, 'field' => 'wallet.balance_minor', 'operator' => 'lt', 'value' => 0]],
        createdByUserId: $f['user']->id,
    ));

    $rule->update(['estimated_monthly_cost_minor' => 100, 'estimated_monthly_currency' => 'USD']);
    app(ActivateAutomationRuleAction::class)->execute($rule->id);

    $dispatched = app(HandleAutomationEventAction::class)->execute('WalletNegative', new WalletNegative($wallet));

    expect($dispatched)->toBe(1)
        ->and(RuleExecution::where('trigger_source', 'WalletNegative')->where('matched', true)->exists())->toBeTrue();
});

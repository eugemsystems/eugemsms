<?php

use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
use Modules\Comms\Livewire\Automation\Builder;
use Modules\Comms\Livewire\Automation\ExecutionLog;
use Modules\Comms\Livewire\Automation\Index;
use Modules\Comms\Livewire\Automation\ScanRuns;
use Modules\Comms\Livewire\Automation\Variants;
use Modules\Comms\Models\AutomationRule;
use Modules\Comms\Models\RuleCondition;
use Modules\Comms\Models\RuleExecution;
use Modules\Comms\Models\RuleTemplateVariant;
use Modules\Comms\Models\ScanRun;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\DataObjects\Notifications\NotificationKeyDefinition;
use Modules\Core\Domain\Registry\NotificationKeyRegistry;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\Notification;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;
use Modules\Finance\Models\Invoice;
use Modules\People\Models\Guardian;
use Modules\People\Models\Student;
use Modules\People\Models\StudentGuardian;

/**
 * Book I COM-02 admin-UI pass. Own, distinctly-named helpers (the
 * sibling backend test file already owns `com02Fixture`).
 *
 * @return array<string, mixed>
 */
function com02AdminFixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);

    NotificationKeyRegistry::register(new NotificationKeyDefinition(
        key: 'fee.overdue.stage_2', variables: ['invoice.balance_minor'],
        defaultChannels: ['sms'], defaultAudience: 'guardian', isTransactional: true,
    ));
    NotificationKeyRegistry::register(new NotificationKeyDefinition(
        key: 'fee.overdue.stage_2_friendly', variables: ['invoice.balance_minor'],
        defaultChannels: ['sms'], defaultAudience: 'guardian', isTransactional: true,
    ));

    return ['school' => $school];
}

/**
 * @param  array<string, mixed>  $f
 */
function com02AdminUser(array $f, string ...$permissionNames): User
{
    $user = User::factory()->create();
    $user->schools()->attach($f['school'], ['status' => 'active']);

    $grants = array_map(function (string $permissionName): PermissionGrantData {
        [$moduleCode, $action] = explode('.', $permissionName);

        $permission = Permission::firstOrCreate(
            ['name' => $permissionName],
            ['guard_name' => 'web', 'module_code' => strtoupper($moduleCode), 'resource' => $action, 'action' => $action],
        );

        return new PermissionGrantData($permission->id, PermissionScope::School);
    }, $permissionNames);

    app(UpdateUserPermissionsAction::class)->execute(new UserPermissionData(
        userId: $user->id, schoolId: $f['school']->id, grants: $grants,
    ));

    return $user;
}

/**
 * @param  array<string, mixed>  $f
 */
function com02AdminOverdueInvoice(array $f, int $daysOverdue = 30): Invoice
{
    $student = Student::factory()->for($f['school'])->create();
    $guardian = Guardian::factory()->for($f['school'])->create(['primary_phone' => '0771234567', 'email' => 'guardian@example.com']);
    StudentGuardian::factory()->for($f['school'])->create([
        'student_id' => $student->id, 'guardian_id' => $guardian->id, 'is_fee_responsible' => true, 'status' => 'active',
    ]);

    return Invoice::factory()->for($f['school'])->create([
        'student_id' => $student->id,
        'due_date' => now()->subDays($daysOverdue)->toDateString(),
        'balance_minor' => 10000,
        'status' => 'overdue',
    ]);
}

it('refuses every COM-02 screen to a user without its permission', function (string $component): void {
    $f = com02AdminFixture();
    $outsider = User::factory()->create();
    $outsider->schools()->attach($f['school'], ['status' => 'active']);

    Livewire::actingAs($outsider)->test($component, ['school' => $f['school']])->assertForbidden();
})->with([
    'library' => Index::class,
    'builder' => Builder::class,
    'executions' => ExecutionLog::class,
    'scans' => ScanRuns::class,
    'variants' => Variants::class,
]);

it('renders every COM-02 screen for a fully-permissioned user', function (): void {
    $f = com02AdminFixture();
    $user = com02AdminUser($f, 'automation.view', 'automation.manage');

    foreach ([Index::class, Builder::class, ExecutionLog::class, ScanRuns::class, Variants::class] as $component) {
        Livewire::actingAs($user)->test($component, ['school' => $f['school']])->assertOk();
    }
});

it('saves a scan rule INACTIVE with typed condition values (BR-COM-02-004)', function (): void {
    $f = com02AdminFixture();
    $user = com02AdminUser($f, 'automation.view', 'automation.manage');

    Livewire::actingAs($user)->test(Builder::class, ['school' => $f['school']])
        ->set('name', 'Fee overdue — 30 day notice')
        ->set('notificationKey', 'fee.overdue.stage_2')
        ->set('triggerType', 'scheduled_scan')
        ->set('scanEntity', 'invoice')
        ->set('scheduleCron', '0 8 * * *')
        ->set('throttleKey', 'invoice.id')
        ->set('throttleWindowHours', '168')
        ->set('conditions', [
            ['group_id' => 1, 'field' => 'invoice.balance_minor', 'operator' => 'gt', 'value' => '0'],
            ['group_id' => 1, 'field' => 'invoice.days_overdue', 'operator' => 'gte', 'value' => '30'],
            ['group_id' => 2, 'field' => 'invoice.status', 'operator' => 'in', 'value' => 'overdue, written_off'],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $rule = AutomationRule::where('school_id', $f['school']->id)->firstOrFail();
    expect($rule->is_active)->toBeFalse()
        ->and($rule->throttle_window_hours)->toBe(168);

    $byField = RuleCondition::where('rule_id', $rule->id)->get()->keyBy('field');
    expect($byField['invoice.days_overdue']->value)->toBe(30)
        ->and($byField['invoice.status']->value)->toBe(['overdue', 'written_off'])
        ->and($byField['invoice.status']->group_id)->toBe(2);
});

it('refuses a field the entity has not exposed for automation, saving nothing (AC-COM-02-003)', function (): void {
    $f = com02AdminFixture();
    $user = com02AdminUser($f, 'automation.manage');

    Livewire::actingAs($user)->test(Builder::class, ['school' => $f['school']])
        ->set('name', 'Bad rule')
        ->set('notificationKey', 'fee.overdue.stage_2')
        ->set('scanEntity', 'invoice')
        ->set('conditions', [['group_id' => 1, 'field' => 'student.has_active_payment_plan', 'operator' => 'eq', 'value' => 'false']])
        ->call('save')
        ->assertHasErrors(['conditions']);

    expect(AutomationRule::where('school_id', $f['school']->id)->count())->toBe(0);
});

it('refuses an event no module publishes, a bad cron, an unknown notification key and a duplicate name', function (): void {
    $f = com02AdminFixture();
    $user = com02AdminUser($f, 'automation.manage');
    AutomationRule::factory()->create(['school_id' => $f['school']->id, 'name' => 'Existing rule']);
    SchoolContext::set($f['school']);

    Livewire::actingAs($user)->test(Builder::class, ['school' => $f['school']])
        ->set('name', 'Event rule')
        ->set('notificationKey', 'fee.overdue.stage_2')
        ->set('triggerType', 'event')
        ->set('eventName', 'NoSuchEvent')
        ->set('conditions', [])
        ->call('save')
        ->assertHasErrors(['conditions']);

    Livewire::actingAs($user)->test(Builder::class, ['school' => $f['school']])
        ->set('name', 'Existing rule')
        ->set('notificationKey', 'not.registered')
        ->set('scanEntity', 'invoice')
        ->set('scheduleCron', 'every day')
        ->set('conditions', [])
        ->call('save')
        ->assertHasErrors(['name', 'notificationKey', 'scheduleCron']);
});

it('never lets a rule be activated without a reviewed cost estimate (BR-COM-02-008, AC-COM-02-005)', function (): void {
    $f = com02AdminFixture();
    $user = com02AdminUser($f, 'automation.manage');
    $rule = AutomationRule::factory()->create(['school_id' => $f['school']->id]);
    SchoolContext::set($f['school']);

    $component = Livewire::actingAs($user)->test(Builder::class, ['school' => $f['school'], 'rule' => $rule->ulid]);

    $component->call('activate')->assertDispatched('toast', variant: 'danger');
    expect($rule->fresh()->is_active)->toBeFalse();

    // A fresh request: the school context does not survive between Livewire calls in production.
    SchoolContext::clear();
    $component->call('estimateCost');
    expect($rule->fresh()->estimated_monthly_cost_minor)->not->toBeNull();

    SchoolContext::clear();
    $component->call('activate');
    expect($rule->fresh()->is_active)->toBeTrue();

    SchoolContext::clear();
    $component->call('deactivate');
    expect($rule->fresh()->is_active)->toBeFalse();
});

it('previews who would receive what without dispatching or logging anything (AC-COM-02-004)', function (): void {
    $f = com02AdminFixture();
    $user = com02AdminUser($f, 'automation.manage');
    com02AdminOverdueInvoice($f);

    $rule = AutomationRule::factory()->create(['school_id' => $f['school']->id]);
    RuleCondition::create(['rule_id' => $rule->id, 'group_id' => 1, 'group_logic' => 'AND', 'field' => 'invoice.days_overdue', 'operator' => 'gte', 'value' => 30]);
    SchoolContext::set($f['school']);

    $component = Livewire::actingAs($user)->test(Builder::class, ['school' => $f['school'], 'rule' => $rule->ulid]);
    SchoolContext::clear();
    $component->call('preview');

    expect($component->get('previewScanned'))->toBeGreaterThanOrEqual(1)
        ->and($component->get('previewMatches'))->toHaveCount(1)
        ->and(RuleExecution::count())->toBe(0)
        ->and(Notification::count())->toBe(0);
});

it('404s a rule belonging to another school', function (): void {
    $f = com02AdminFixture();
    $user = com02AdminUser($f, 'automation.manage');
    $other = School::factory()->create();
    $foreign = AutomationRule::factory()->create(['school_id' => $other->id]);
    SchoolContext::set($f['school']);

    expect(fn () => Livewire::actingAs($user)->test(Builder::class, ['school' => $f['school'], 'rule' => $foreign->ulid]))
        ->toThrow(ModelNotFoundException::class);
});

it('lists rules and only lets a manager deactivate one (BR-COM-02-011)', function (): void {
    $f = com02AdminFixture();
    $viewer = com02AdminUser($f, 'automation.view');
    $manager = com02AdminUser($f, 'automation.view', 'automation.manage');
    $rule = AutomationRule::factory()->create(['school_id' => $f['school']->id, 'is_active' => true, 'estimated_monthly_cost_minor' => 1500, 'estimated_monthly_currency' => 'USD']);
    SchoolContext::set($f['school']);

    Livewire::actingAs($viewer)->test(Index::class, ['school' => $f['school']])
        ->assertSee($rule->name)
        ->assertSee('15.00')
        ->call('deactivate', $rule->id)
        ->assertForbidden();
    expect($rule->fresh()->is_active)->toBeTrue();

    Livewire::actingAs($manager)->test(Index::class, ['school' => $f['school']])->call('deactivate', $rule->id);
    expect($rule->fresh()->is_active)->toBeFalse();
});

it('breaks execution outcomes down into sent, throttled and not matched (BR-COM-02-006)', function (): void {
    $f = com02AdminFixture();
    $user = com02AdminUser($f, 'automation.view');
    $rule = AutomationRule::factory()->create(['school_id' => $f['school']->id]);
    $notification = Notification::factory()->create(['school_id' => $f['school']->id]);

    RuleExecution::factory()->create(['school_id' => $f['school']->id, 'rule_id' => $rule->id, 'matched' => true, 'notification_id' => $notification->id]);
    RuleExecution::factory()->count(2)->create(['school_id' => $f['school']->id, 'rule_id' => $rule->id, 'matched' => true, 'skip_reason' => 'throttled']);
    RuleExecution::factory()->count(3)->create(['school_id' => $f['school']->id, 'rule_id' => $rule->id, 'matched' => false, 'skip_reason' => 'condition_not_met']);
    SchoolContext::set($f['school']);

    $component = Livewire::actingAs($user)->test(ExecutionLog::class, ['school' => $f['school']]);

    expect($component->viewData('sent'))->toBe(1)
        ->and($component->viewData('throttled'))->toBe(2)
        ->and($component->viewData('notMatched'))->toBe(3);
});

it('warns when a rule matched nothing in its last three scans (AC-COM-02-007)', function (): void {
    $f = com02AdminFixture();
    $user = com02AdminUser($f, 'automation.view');
    $rule = AutomationRule::factory()->create(['school_id' => $f['school']->id, 'name' => 'Silent rule']);
    ScanRun::factory()->count(3)->create(['school_id' => $f['school']->id, 'rule_id' => $rule->id, 'records_matched' => 0, 'notifications_dispatched' => 0]);
    SchoolContext::set($f['school']);

    Livewire::actingAs($user)->test(ScanRuns::class, ['school' => $f['school']])
        ->assertSee('Silent rule')
        ->assertSee('matched nothing in its last three runs');
});

it('adds an A/B variant but caps total weight at 100% and refuses a duplicate key (BR-COM-02-009/010)', function (): void {
    $f = com02AdminFixture();
    $manager = com02AdminUser($f, 'automation.view', 'automation.manage');
    $rule = AutomationRule::factory()->create(['school_id' => $f['school']->id]);
    SchoolContext::set($f['school']);

    $component = Livewire::actingAs($manager)->test(Variants::class, ['school' => $f['school']])
        ->set('ruleId', $rule->id)
        ->set('variantKey', 'B')
        ->set('templateKey', 'fee.overdue.stage_2_friendly')
        ->set('weightPercent', 60)
        ->call('addVariant')
        ->assertHasNoErrors();

    expect(RuleTemplateVariant::where('rule_id', $rule->id)->count())->toBe(1);

    $component->set('variantKey', 'C')->set('templateKey', 'fee.overdue.stage_2_friendly')->set('weightPercent', 50)->call('addVariant')->assertHasErrors(['weightPercent']);
    $component->set('variantKey', 'B')->set('templateKey', 'fee.overdue.stage_2_friendly')->set('weightPercent', 10)->call('addVariant')->assertHasErrors(['variantKey']);

    expect(RuleTemplateVariant::where('rule_id', $rule->id)->count())->toBe(1);
});

it('lets a view-only user see variants but not add one', function (): void {
    $f = com02AdminFixture();
    $viewer = com02AdminUser($f, 'automation.view');
    $rule = AutomationRule::factory()->create(['school_id' => $f['school']->id]);
    SchoolContext::set($f['school']);

    Livewire::actingAs($viewer)->test(Variants::class, ['school' => $f['school']])
        ->assertDontSee('Add variant')
        ->set('ruleId', $rule->id)
        ->set('variantKey', 'B')
        ->set('templateKey', 'fee.overdue.stage_2_friendly')
        ->call('addVariant')
        ->assertForbidden();
});

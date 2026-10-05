<?php

use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\Auth\UserType;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\ActivityLogEntry;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;
use Modules\Core\Models\Tenant;
use Modules\Saas\Domain\Actions\CreateSubscriptionAction;
use Modules\Saas\Domain\Actions\IssueLicenceKeyAction;
use Modules\Saas\Domain\Actions\IssueTenantInvoiceAction;
use Modules\Saas\Domain\Actions\RecordTenantPaymentAction;
use Modules\Saas\Domain\DataObjects\CreateSubscriptionData;
use Modules\Saas\Domain\DataObjects\IssueLicenceKeyData;
use Modules\Saas\Domain\DataObjects\IssueTenantInvoiceData;
use Modules\Saas\Domain\DataObjects\RecordTenantPaymentData;
use Modules\Saas\Livewire\Tenant\Subscription\MySubscription;
use Modules\Saas\Livewire\Vendor\Billing\Invoices;
use Modules\Saas\Livewire\Vendor\Licensing\Keys;
use Modules\Saas\Livewire\Vendor\Subscription\Manage;
use Modules\Saas\Livewire\Vendor\Subscription\Plans;
use Modules\Saas\Models\LicenceKey;
use Modules\Saas\Models\Subscription;
use Modules\Saas\Models\SubscriptionChange;
use Modules\Saas\Models\SubscriptionPlan;
use Modules\Saas\Models\TenantInvoice;

/**
 * Book J SAA-01 admin-UI pass. Own, distinctly-named helpers.
 *
 * @return array<string, mixed>
 */
function saa01AdminFixture(): array
{
    $tenant = Tenant::factory()->create();
    $school = School::factory()->create(['tenant_id' => $tenant->id, 'base_currency' => 'USD']);
    SchoolContext::set($school);

    $basic = SubscriptionPlan::factory()->create(['code' => 'BASIC', 'name' => 'Basic', 'price_per_learner_minor' => 100]);
    $premium = SubscriptionPlan::factory()->tier('premium')->create(['code' => 'PREM', 'name' => 'Premium', 'price_per_learner_minor' => 300]);
    $subscription = Subscription::factory()->create(['tenant_id' => $tenant->id, 'plan_id' => $basic->id, 'covered_school_ids' => [$school->id], 'learner_count_at_billing' => 100]);

    return compact('tenant', 'school', 'basic', 'premium', 'subscription');
}

/**
 * @param  array<string, mixed>  $f
 */
function saa01AdminOwner(array $f, string ...$permissionNames): User
{
    $user = User::factory()->create(['tenant_id' => $f['tenant']->id]);
    $user->schools()->attach($f['school'], ['status' => 'active']);

    $grants = array_map(function (string $permissionName): PermissionGrantData {
        $parts = explode('.', $permissionName);
        $action = end($parts);

        $permission = Permission::firstOrCreate(
            ['name' => $permissionName],
            ['guard_name' => 'web', 'module_code' => strtoupper($parts[0]), 'resource' => count($parts) > 2 ? $parts[1] : $action, 'action' => $action],
        );

        return new PermissionGrantData($permission->id, PermissionScope::School);
    }, $permissionNames);

    app(UpdateUserPermissionsAction::class)->execute(new UserPermissionData(userId: $user->id, schoolId: $f['school']->id, grants: $grants));

    return $user;
}

function saa01AdminVendor(): User
{
    return User::factory()->create(['user_type' => UserType::Vendor, 'two_factor_confirmed_at' => now()]);
}

it('refuses every vendor component to a school user, whatever their permissions (AC-SAA-02-001)', function (string $component): void {
    $f = saa01AdminFixture();
    $owner = saa01AdminOwner($f, 'subscription.view', 'subscription.manage');

    Livewire::actingAs($owner)->test($component)->assertForbidden();
})->with([
    'plans' => Plans::class,
    'manage' => Manage::class,
    'invoices' => Invoices::class,
    'keys' => Keys::class,
]);

it('keeps the vendor console unreachable over HTTP for a school user and a guest', function (): void {
    $f = saa01AdminFixture();
    $owner = saa01AdminOwner($f, 'subscription.view', 'subscription.manage');

    $this->actingAs($owner)->get('/vendor/subscriptions')->assertForbidden();
    auth()->logout();
    $this->get('/vendor/subscriptions')->assertRedirect();
});

it('shows a tenant its own plan, usage and invoices only (AC-SAA-01-006)', function (): void {
    $f = saa01AdminFixture();
    $owner = saa01AdminOwner($f, 'subscription.view');
    $invoice = app(IssueTenantInvoiceAction::class)->execute(new IssueTenantInvoiceData($f['subscription']->id, now()->format('Y-m')));

    $other = Subscription::factory()->create(['plan_id' => $f['premium']->id]);
    $foreignInvoice = app(IssueTenantInvoiceAction::class)->execute(new IssueTenantInvoiceData($other->id, now()->format('Y-m')));

    Livewire::actingAs($owner)->test(MySubscription::class, ['school' => $f['school']])
        ->assertSee('Basic')->assertSee($invoice->invoice_number)->assertDontSee($foreignInvoice->invoice_number);
});

it('refuses a user from another tenant, even at this school’s URL', function (): void {
    $f = saa01AdminFixture();
    $stranger = User::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);
    $stranger->schools()->attach($f['school'], ['status' => 'active']);
    app(UpdateUserPermissionsAction::class)->execute(new UserPermissionData(userId: $stranger->id, schoolId: $f['school']->id, grants: [
        new PermissionGrantData(Permission::firstOrCreate(['name' => 'subscription.view'], ['guard_name' => 'web', 'module_code' => 'SUBSCRIPTION', 'resource' => 'view', 'action' => 'view'])->id, PermissionScope::School),
    ]));

    Livewire::actingAs($stranger)->test(MySubscription::class, ['school' => $f['school']])->assertForbidden();
});

it('upgrades immediately with a prorated invoice and schedules a downgrade for renewal (AC-SAA-01-003)', function (): void {
    $f = saa01AdminFixture();
    $owner = saa01AdminOwner($f, 'subscription.view', 'subscription.manage');

    $component = Livewire::actingAs($owner)->test(MySubscription::class, ['school' => $f['school']])->call('changePlan', $f['premium']->id);

    expect($f['subscription']->fresh()->plan_id)->toBe($f['premium']->id)
        ->and(SubscriptionChange::where('change_type', 'upgrade')->count())->toBe(1)
        ->and(TenantInvoice::where('subscription_id', $f['subscription']->id)->count())->toBe(1);

    $component->call('changePlan', $f['basic']->id);

    expect($f['subscription']->fresh()->plan_id)->toBe($f['premium']->id)
        ->and(SubscriptionChange::where('change_type', 'downgrade')->count())->toBe(1);
});

it('will not change plan without subscription.manage, to the same or a withdrawn plan, or while suspended', function (): void {
    $f = saa01AdminFixture();
    $viewer = saa01AdminOwner($f, 'subscription.view');
    $manager = saa01AdminOwner($f, 'subscription.view', 'subscription.manage');

    Livewire::actingAs($viewer)->test(MySubscription::class, ['school' => $f['school']])->call('changePlan', $f['premium']->id)->assertForbidden();

    $component = Livewire::actingAs($manager)->test(MySubscription::class, ['school' => $f['school']]);

    $component->call('changePlan', $f['basic']->id);
    expect(SubscriptionChange::count())->toBe(0);

    $f['premium']->update(['is_active' => false]);
    expect(fn () => $component->call('changePlan', $f['premium']->id))->toThrow(ModelNotFoundException::class);

    $f['premium']->update(['is_active' => true]);
    $f['subscription']->update(['status' => 'suspended']);
    $component->call('changePlan', $f['premium']->id);

    expect($f['subscription']->fresh()->plan_id)->toBe($f['basic']->id);
});

it('opens a subscription, entitles its schools, and audits the vendor’s action (BR-SAA-02-007)', function (): void {
    $f = saa01AdminFixture();
    $vendor = saa01AdminVendor();
    $newTenant = Tenant::factory()->create();
    $newSchool = School::factory()->create(['tenant_id' => $newTenant->id]);

    Livewire::actingAs($vendor)->test(Manage::class)
        ->set('tenantId', $newTenant->id)->set('planId', $f['basic']->id)->set('schoolIds', [$newSchool->id])->set('learnerCount', 120)
        ->call('create')->assertHasNoErrors();

    expect(Subscription::where('tenant_id', $newTenant->id)->exists())->toBeTrue();

    $entry = ActivityLogEntry::where('log_name', 'vendor_console')->where('event', 'subscription.opened')->first();

    expect($entry)->not->toBeNull()->and($entry->causer_id)->toBe($vendor->id)->and($entry->subject_id)->toBe($newTenant->id);
});

it('refuses to cover another tenant’s school, a withdrawn plan, or a second live subscription', function (): void {
    $f = saa01AdminFixture();
    $vendor = saa01AdminVendor();
    $newTenant = Tenant::factory()->create();
    $foreignSchool = School::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);
    $ownSchool = School::factory()->create(['tenant_id' => $newTenant->id]);

    $component = Livewire::actingAs($vendor)->test(Manage::class)->set('tenantId', $newTenant->id)->set('planId', $f['basic']->id)->set('schoolIds', [$foreignSchool->id])
        ->call('create')->assertHasErrors('tenantId');

    expect(Subscription::where('tenant_id', $newTenant->id)->count())->toBe(0);

    $f['basic']->update(['is_active' => false]);
    $component->set('schoolIds', [$ownSchool->id])->call('create')->assertHasErrors('tenantId');

    $f['basic']->update(['is_active' => true]);
    $component->set('tenantId', $f['tenant']->id)->set('schoolIds', [$f['school']->id])->call('create')->assertHasErrors('tenantId');
});

it('moves a subscription through its lifecycle, mirroring the tenant, and refuses an illegal move', function (): void {
    $f = saa01AdminFixture();
    $vendor = saa01AdminVendor();

    $component = Livewire::actingAs($vendor)->test(Manage::class)->call('move', $f['subscription']->id, 'suspend');

    expect($f['subscription']->fresh()->status)->toBe('suspended')->and($f['tenant']->fresh()->status)->toBe('suspended');

    $component->call('move', $f['subscription']->id, 'reactivate');
    expect($f['subscription']->fresh()->status)->toBe('active');

    $component->call('move', $f['subscription']->id, 'cancel');
    expect($f['subscription']->fresh()->status)->toBe('active');

    $component->set('cancelReason', 'Contract ended')->call('move', $f['subscription']->id, 'cancel');
    expect($f['subscription']->fresh()->status)->toBe('cancelled');

    $component->call('move', $f['subscription']->id, 'reactivate')->assertStatus(422);
    expect($f['subscription']->fresh()->status)->toBe('cancelled');
});

it('withdraws and re-offers a plan without touching existing subscribers', function (): void {
    $f = saa01AdminFixture();
    $vendor = saa01AdminVendor();

    Livewire::actingAs($vendor)->test(Plans::class)->call('setActive', $f['basic']->id, false);

    expect($f['basic']->fresh()->is_active)->toBeFalse()->and($f['subscription']->fresh()->plan_id)->toBe($f['basic']->id);
});

it('issues an invoice and records payments in the invoice’s own currency, never on a paid one', function (): void {
    $f = saa01AdminFixture();
    $vendor = saa01AdminVendor();

    $component = Livewire::actingAs($vendor)->test(Invoices::class)->set('subscriptionId', $f['subscription']->id)->call('issue');
    $invoice = TenantInvoice::firstOrFail();

    expect($invoice->total_minor)->toBe(10000);

    $component->call('startPayment', $invoice->id)->set('amount', '40.00')->call('recordPayment')->assertHasNoErrors();
    expect($invoice->fresh()->status)->toBe('issued')->and($invoice->fresh()->amountPaidMinor())->toBe(4000);

    expect(fn () => app(RecordTenantPaymentAction::class)->execute(new RecordTenantPaymentData($invoice->id, 6000, 'ZWG', 'cash')))->toThrow(InvalidArgumentException::class);
    expect(fn () => app(RecordTenantPaymentAction::class)->execute(new RecordTenantPaymentData($invoice->id, 0, 'USD', 'cash')))->toThrow(InvalidArgumentException::class);

    $component->call('startPayment', $invoice->id)->set('amount', '60.00')->call('recordPayment');
    expect($invoice->fresh()->status)->toBe('paid');

    expect(fn () => $component->call('startPayment', $invoice->id))->toThrow(ModelNotFoundException::class);
});

it('binds a licence key to the tenant that owns the subscription', function (): void {
    $f = saa01AdminFixture();
    $vendor = saa01AdminVendor();

    Livewire::actingAs($vendor)->test(Keys::class)->set('subscriptionId', $f['subscription']->id)->call('issue')->assertHasNoErrors();

    expect(LicenceKey::firstOrFail()->tenant_id)->toBe($f['tenant']->id);

    $otherTenant = Tenant::factory()->create();
    expect(fn () => app(IssueLicenceKeyAction::class)->execute(new IssueLicenceKeyData($otherTenant->id, $f['subscription']->id)))->toThrow(ModelNotFoundException::class);
});

it('refuses to open a subscription over another tenant’s schools at Action level', function (): void {
    $f = saa01AdminFixture();
    $other = Tenant::factory()->create();

    expect(fn () => app(CreateSubscriptionAction::class)->execute(new CreateSubscriptionData($other->id, $f['basic']->id, [$f['school']->id], 'USD', Carbon::today(), Carbon::today()->addMonth())))
        ->toThrow(InvalidArgumentException::class);
});

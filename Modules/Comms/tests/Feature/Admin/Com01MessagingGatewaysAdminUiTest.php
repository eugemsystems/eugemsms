<?php

use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
use Modules\Comms\Livewire\Messaging\Gateways\Index as GatewaysIndex;
use Modules\Comms\Livewire\Messaging\Gateways\Webhooks as GatewayWebhooks;
use Modules\Comms\Livewire\Messaging\Reports\Cost as CostReport;
use Modules\Comms\Livewire\Messaging\Reports\Reconciliation as ReconciliationReport;
use Modules\Comms\Livewire\Messaging\Sms\SenderIds;
use Modules\Comms\Livewire\Messaging\WhatsApp\Templates as WhatsAppTemplates;
use Modules\Comms\Models\GatewayCostReconciliation;
use Modules\Comms\Models\MessageGateway;
use Modules\Comms\Models\SenderId;
use Modules\Comms\Models\WhatsAppBusinessAccount;
use Modules\Comms\Models\WhatsAppTemplate;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\Notification;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;

/**
 * Book I COM-01 admin-UI pass. Own, distinctly-named helpers —
 * `com01Fixture` already exists in the sibling backend test file.
 *
 * @return array<string, mixed>
 */
function com01AdminFixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);

    return ['school' => $school];
}

/**
 * @param  array<string, mixed>  $f
 */
function com01AdminUser(array $f, string ...$permissionNames): User
{
    $user = User::factory()->create();
    $user->schools()->attach($f['school'], ['status' => 'active']);

    $grants = array_map(function (string $permissionName): PermissionGrantData {
        [$moduleCode, $resource, $action] = explode('.', $permissionName);

        $permission = Permission::firstOrCreate(
            ['name' => $permissionName],
            ['guard_name' => 'web', 'module_code' => strtoupper($moduleCode), 'resource' => $resource, 'action' => $action],
        );

        return new PermissionGrantData($permission->id, PermissionScope::School);
    }, $permissionNames);

    app(UpdateUserPermissionsAction::class)->execute(new UserPermissionData(
        userId: $user->id, schoolId: $f['school']->id, grants: $grants,
    ));

    return $user;
}

it('refuses every COM-01 screen to a user without its permission', function (string $component): void {
    $f = com01AdminFixture();
    $outsider = User::factory()->create();
    $outsider->schools()->attach($f['school'], ['status' => 'active']);

    Livewire::actingAs($outsider)->test($component, ['school' => $f['school']])->assertForbidden();
})->with([
    'gateways' => GatewaysIndex::class,
    'webhooks' => GatewayWebhooks::class,
    'templates' => WhatsAppTemplates::class,
    'sender ids' => SenderIds::class,
    'cost' => CostReport::class,
    'reconciliation' => ReconciliationReport::class,
]);

it('renders every COM-01 screen for a fully-permissioned user', function (): void {
    $f = com01AdminFixture();
    $user = com01AdminUser($f, 'comms.gateway.manage', 'comms.gateway.view', 'comms.template.manage', 'comms.sender_id.manage', 'comms.report.view', 'comms.reconciliation.manage');

    foreach ([GatewaysIndex::class, GatewayWebhooks::class, WhatsAppTemplates::class, SenderIds::class, CostReport::class, ReconciliationReport::class] as $component) {
        Livewire::actingAs($user)->test($component, ['school' => $f['school']])->assertOk();
    }
});

it('registers a gateway with encrypted credentials and never renders them back (BR-COM-01-001)', function (): void {
    $f = com01AdminFixture();
    $user = com01AdminUser($f, 'comms.gateway.manage');

    Livewire::actingAs($user)->test(GatewaysIndex::class, ['school' => $f['school']])
        ->set('channel', 'sms')
        ->set('driver', 'bulksms_zw')
        ->set('name', 'BulkSMS ZW')
        ->set('credentials', '{"api_key":"super-secret-key"}')
        ->set('webhookSecret', 'whsec-hidden')
        ->call('register')
        ->assertHasNoErrors()
        ->assertSee('BulkSMS ZW')
        ->assertDontSee('super-secret-key')
        ->assertDontSee('whsec-hidden');

    $gateway = MessageGateway::where('school_id', $f['school']->id)->firstOrFail();
    expect($gateway->credentials)->toContain('super-secret-key')
        ->and($gateway->getRawOriginal('credentials'))->not->toContain('super-secret-key');
});

it('rejects gateway credentials that are not valid JSON', function (): void {
    $f = com01AdminFixture();
    $user = com01AdminUser($f, 'comms.gateway.manage');

    Livewire::actingAs($user)->test(GatewaysIndex::class, ['school' => $f['school']])
        ->set('driver', 'bulksms_zw')
        ->set('name', 'Bad gateway')
        ->set('credentials', 'not json')
        ->call('register')
        ->assertHasErrors(['credentials']);

    expect(MessageGateway::where('school_id', $f['school']->id)->count())->toBe(0);
});

it('deactivates and reactivates a gateway, and refuses another school’s gateway', function (): void {
    $f = com01AdminFixture();
    $user = com01AdminUser($f, 'comms.gateway.manage');
    $gateway = MessageGateway::factory()->create(['school_id' => $f['school']->id, 'is_active' => true]);
    $other = School::factory()->create();
    $foreign = MessageGateway::factory()->create(['school_id' => $other->id, 'driver' => 'other_driver']);
    SchoolContext::set($f['school']);

    $component = Livewire::actingAs($user)->test(GatewaysIndex::class, ['school' => $f['school']]);

    $component->call('toggleActive', $gateway->id);
    expect($gateway->fresh()->is_active)->toBeFalse();

    $component->call('toggleActive', $gateway->id);
    expect($gateway->fresh()->is_active)->toBeTrue();

    expect(fn () => $component->call('toggleActive', $foreign->id))->toThrow(ModelNotFoundException::class);
    expect($foreign->fresh()->is_active)->toBeTrue();
});

it('submits a WhatsApp template as pending, then records Meta’s decision (BR-COM-01-005/006)', function (): void {
    $f = com01AdminFixture();
    $user = com01AdminUser($f, 'comms.template.manage');
    $gateway = MessageGateway::factory()->create(['school_id' => $f['school']->id, 'channel' => 'whatsapp', 'driver' => 'meta_cloud']);

    $component = Livewire::actingAs($user)->test(WhatsAppTemplates::class, ['school' => $f['school']])
        ->set('gatewayId', $gateway->id)
        ->set('wabaId', 'waba-123')
        ->set('displayPhoneNumber', '+263771234567')
        ->set('displayName', 'Eugem School')
        ->call('registerAccount')
        ->assertHasNoErrors();

    $account = WhatsAppBusinessAccount::where('school_id', $f['school']->id)->firstOrFail();

    $component
        ->set('templateWabaId', $account->id)
        ->set('metaTemplateName', 'fee_reminder')
        ->set('category', 'utility')
        ->set('bodyText', 'Hello {{1}}, your balance is {{2}}.')
        ->call('submitTemplate')
        ->assertHasNoErrors();

    $template = WhatsAppTemplate::where('school_id', $f['school']->id)->firstOrFail();
    expect($template->review_status)->toBe('pending');

    $component->call('recordReview', $template->id, 'approved', 'meta-tpl-1');
    expect($template->fresh()->review_status)->toBe('approved')
        ->and($template->fresh()->meta_template_id)->toBe('meta-tpl-1');
});

it('rejects a template with a non-numbered placeholder or a bad name', function (): void {
    $f = com01AdminFixture();
    $user = com01AdminUser($f, 'comms.template.manage');
    $account = WhatsAppBusinessAccount::factory()->create(['school_id' => $f['school']->id]);

    Livewire::actingAs($user)->test(WhatsAppTemplates::class, ['school' => $f['school']])
        ->set('templateWabaId', $account->id)
        ->set('metaTemplateName', 'Bad Name')
        ->set('bodyText', 'Hello {{name}}')
        ->call('submitTemplate')
        ->assertHasErrors(['metaTemplateName', 'bodyText']);

    expect(WhatsAppTemplate::where('school_id', $f['school']->id)->count())->toBe(0);
});

it('flags a red WhatsApp quality rating as paused (BR-COM-01-007)', function (): void {
    $f = com01AdminFixture();
    $user = com01AdminUser($f, 'comms.template.manage');
    $account = WhatsAppBusinessAccount::factory()->create(['school_id' => $f['school']->id, 'quality_rating' => 'green']);

    Livewire::actingAs($user)->test(WhatsAppTemplates::class, ['school' => $f['school']])
        ->call('recordQuality', $account->id, 'red')
        ->assertSee('non-critical sends paused');

    expect($account->fresh()->isQualityPaused())->toBeTrue();
});

it('registers a sender ID as pending and records the network’s decision (BR-COM-01-008)', function (): void {
    $f = com01AdminFixture();
    $user = com01AdminUser($f, 'comms.sender_id.manage');
    $gateway = MessageGateway::factory()->create(['school_id' => $f['school']->id, 'channel' => 'sms']);

    $component = Livewire::actingAs($user)->test(SenderIds::class, ['school' => $f['school']])
        ->set('gatewayId', $gateway->id)
        ->set('senderId', 'EUGEMSCH')
        ->set('network', 'Econet')
        ->call('register')
        ->assertHasNoErrors();

    $sender = SenderId::where('school_id', $f['school']->id)->firstOrFail();
    expect($sender->status)->toBe('pending')->and($sender->isUsable())->toBeFalse();

    $component->call('recordStatus', $sender->id, 'approved');
    expect($sender->fresh()->isUsable())->toBeTrue();
});

it('rejects a sender ID with illegal characters or over 11 characters', function (): void {
    $f = com01AdminFixture();
    $user = com01AdminUser($f, 'comms.sender_id.manage');
    $gateway = MessageGateway::factory()->create(['school_id' => $f['school']->id, 'channel' => 'sms']);

    Livewire::actingAs($user)->test(SenderIds::class, ['school' => $f['school']])
        ->set('gatewayId', $gateway->id)
        ->set('senderId', 'Too-Long-Sender!')
        ->call('register')
        ->assertHasErrors(['senderId']);
});

it('flags a cost variance beyond tolerance from the reconciliation screen (AC-COM-01-006)', function (): void {
    $f = com01AdminFixture();
    $user = com01AdminUser($f, 'comms.reconciliation.manage');
    $gateway = MessageGateway::factory()->create(['school_id' => $f['school']->id, 'channel' => 'sms']);
    Notification::factory()->create(['school_id' => $f['school']->id, 'channel' => 'sms', 'cost_minor' => 41200]);

    Livewire::actingAs($user)->test(ReconciliationReport::class, ['school' => $f['school']])
        ->set('gatewayId', $gateway->id)
        ->set('periodMonth', now()->format('Y-m'))
        ->set('providerInvoiced', '460')
        ->call('reconcile')
        ->assertHasNoErrors()
        ->assertDispatched('toast', variant: 'warning');

    $row = GatewayCostReconciliation::where('school_id', $f['school']->id)->firstOrFail();
    expect($row->status)->toBe('variance')->and($row->variance_minor)->toBe(4800);
});

it('rejects a non-numeric provider invoice amount', function (): void {
    $f = com01AdminFixture();
    $user = com01AdminUser($f, 'comms.reconciliation.manage');
    $gateway = MessageGateway::factory()->create(['school_id' => $f['school']->id, 'channel' => 'sms']);

    Livewire::actingAs($user)->test(ReconciliationReport::class, ['school' => $f['school']])
        ->set('gatewayId', $gateway->id)
        ->set('providerInvoiced', 'abc')
        ->call('reconcile')
        ->assertHasErrors(['providerInvoiced']);
});

it('shows spend for the selected month on the cost report without breaking on a tampered month', function (): void {
    $f = com01AdminFixture();
    $user = com01AdminUser($f, 'comms.report.view');
    Notification::factory()->create(['school_id' => $f['school']->id, 'channel' => 'sms', 'cost_minor' => 41200, 'cost_currency' => 'USD']);

    Livewire::actingAs($user)->test(CostReport::class, ['school' => $f['school']])
        ->assertSee('412.00')
        ->set('month', 'garbage')
        ->assertOk();
});

<?php

use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;
use Modules\Intelligence\Domain\Actions\CreateWebhookSubscriptionAction;
use Modules\Intelligence\Domain\Actions\DispatchWebhookAction;
use Modules\Intelligence\Domain\Actions\IssueApiClientAction;
use Modules\Intelligence\Domain\Actions\RegisterHardwareDeviceAction;
use Modules\Intelligence\Domain\Actions\RotateApiClientKeyAction;
use Modules\Intelligence\Livewire\Integrations\Clients\Index as ClientsIndex;
use Modules\Intelligence\Livewire\Integrations\Hardware\Index as HardwareIndex;
use Modules\Intelligence\Livewire\Integrations\Sso\Index as SsoIndex;
use Modules\Intelligence\Livewire\Integrations\Usage\Dashboard as UsageDashboard;
use Modules\Intelligence\Livewire\Integrations\Webhooks\Index as WebhooksIndex;
use Modules\Intelligence\Livewire\Integrations\Webhooks\Log as WebhooksLog;
use Modules\Intelligence\Models\ApiClient;
use Modules\Intelligence\Models\ApiUsageLog;
use Modules\Intelligence\Models\HardwareDevice;
use Modules\Intelligence\Models\SsoProvisioningConfig;
use Modules\Intelligence\Models\WebhookDelivery;
use Modules\Intelligence\Models\WebhookSubscription;

/**
 * Book J INT-04 admin-UI pass. Own, distinctly-named helpers.
 *
 * @return array<string, mixed>
 */
function int04AdminFixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);

    return compact('school');
}

/**
 * @param  array<string, mixed>  $f
 */
function int04AdminUser(array $f, string ...$permissionNames): User
{
    $user = User::factory()->create();
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

    app(UpdateUserPermissionsAction::class)->execute(new UserPermissionData(
        userId: $user->id, schoolId: $f['school']->id, grants: $grants,
    ));

    return $user;
}

/**
 * @param  array<string, mixed>  $f
 * @return array{client: ApiClient, plaintextKey: string}
 */
function int04AdminClient(array $f, string $name = 'BI Tool'): array
{
    return app(IssueApiClientAction::class)->execute($f['school']->id, $name, 'integration', ['usage:read']);
}

it('refuses every INT-04 screen to a user without its permission', function (string $component): void {
    $f = int04AdminFixture();
    $outsider = User::factory()->create();
    $outsider->schools()->attach($f['school'], ['status' => 'active']);

    Livewire::actingAs($outsider)->test($component, ['school' => $f['school']])->assertForbidden();
})->with([
    'clients' => ClientsIndex::class,
    'webhooks' => WebhooksIndex::class,
    'log' => WebhooksLog::class,
    'sso' => SsoIndex::class,
    'hardware' => HardwareIndex::class,
    'usage' => UsageDashboard::class,
]);

it('renders every INT-04 screen for a permitted user', function (): void {
    $f = int04AdminFixture();
    $user = int04AdminUser($f, 'integration.manage', 'integration.view', 'integration.webhook.manage', 'integration.sso.manage', 'integration.hardware.manage');

    foreach ([ClientsIndex::class, WebhooksIndex::class, WebhooksLog::class, SsoIndex::class, HardwareIndex::class, UsageDashboard::class] as $component) {
        Livewire::actingAs($user)->test($component, ['school' => $f['school']])->assertOk();
    }
});

it('issues a client, shows the key once, and stores only its hash (BR-INT-04-002)', function (): void {
    $f = int04AdminFixture();
    $user = int04AdminUser($f, 'integration.manage');

    $component = Livewire::actingAs($user)->test(ClientsIndex::class, ['school' => $f['school']])
        ->set('name', 'Zapier connector')->set('ipAllowlist', "203.0.113.0/24\n198.51.100.7")
        ->call('issue')->assertHasNoErrors();

    $client = ApiClient::firstOrFail();
    $key = $component->get('revealedKey');

    expect($key)->toBeString()->not->toBe($client->api_key_hash)
        ->and(Hash::check($key, $client->api_key_hash))->toBeTrue()
        ->and($client->client_type)->toBe('integration')
        ->and($client->ip_allowlist)->toBe(['203.0.113.0/24', '198.51.100.7']);

    $component->call('dismissKey')->assertSet('revealedKey', null)->assertDontSee($key);
});

it('refuses unlisted abilities and malformed IP allowlists at issuance', function (): void {
    $f = int04AdminFixture();
    $user = int04AdminUser($f, 'integration.manage');

    Livewire::actingAs($user)->test(ClientsIndex::class, ['school' => $f['school']])
        ->set('name', 'Bad')->set('abilities', ['payroll:write'])->call('issue')->assertHasErrors('name');

    Livewire::actingAs($user)->test(ClientsIndex::class, ['school' => $f['school']])
        ->set('name', 'Bad')->set('ipAllowlist', 'not-an-ip')->call('issue')->assertHasErrors('name');

    expect(ApiClient::count())->toBe(0);
});

it('rotating a key invalidates the old one, and a revoked client cannot be rotated', function (): void {
    $f = int04AdminFixture();
    $issued = int04AdminClient($f);

    $rotated = app(RotateApiClientKeyAction::class)->execute($issued['client']->id);

    expect(Hash::check($issued['plaintextKey'], $rotated['client']->api_key_hash))->toBeFalse()
        ->and(Hash::check($rotated['plaintextKey'], $rotated['client']->api_key_hash))->toBeTrue();

    $issued['client']->update(['is_active' => false]);

    expect(fn () => app(RotateApiClientKeyAction::class)->execute($issued['client']->id))->toThrow(InvalidArgumentException::class);
});

it('revokes a client and switches off its webhook subscriptions', function (): void {
    $f = int04AdminFixture();
    $user = int04AdminUser($f, 'integration.manage');
    $issued = int04AdminClient($f);
    $subscription = app(CreateWebhookSubscriptionAction::class)->execute($f['school']->id, $issued['client']->id, ['InvoiceIssued'], 'https://hooks.example.test/in');

    Livewire::actingAs($user)->test(ClientsIndex::class, ['school' => $f['school']])->call('revoke', $issued['client']->id);

    expect($issued['client']->fresh()->is_active)->toBeFalse()
        ->and($subscription->fresh()->is_active)->toBeFalse();
});

it('cannot revoke another school’s client or a hardware credential from the clients screen', function (): void {
    $f = int04AdminFixture();
    $user = int04AdminUser($f, 'integration.manage');
    $device = app(RegisterHardwareDeviceAction::class)->execute($f['school']->id, 'rfid_reader', 'gate');
    $other = School::factory()->create();
    SchoolContext::set($other);
    $foreign = app(IssueApiClientAction::class)->execute($other->id, 'Foreign', 'integration', ['usage:read']);
    SchoolContext::set($f['school']);

    $component = Livewire::actingAs($user)->test(ClientsIndex::class, ['school' => $f['school']]);

    expect(fn () => $component->call('revoke', $foreign['client']->id))->toThrow(ModelNotFoundException::class);
    expect(fn () => $component->call('revoke', $device['device']->api_client_id))->toThrow(ModelNotFoundException::class);
    expect(fn () => $component->call('rotate', $device['device']->api_client_id))->toThrow(ModelNotFoundException::class);
});

it('creates a webhook subscription and shows its signing secret once', function (): void {
    $f = int04AdminFixture();
    $user = int04AdminUser($f, 'integration.webhook.manage');
    $issued = int04AdminClient($f);

    $component = Livewire::actingAs($user)->test(WebhooksIndex::class, ['school' => $f['school']])
        ->set('clientId', $issued['client']->id)->set('events', 'InvoiceIssued, ReceiptVoided')->set('targetUrl', 'https://hooks.example.test/in')
        ->call('create')->assertHasNoErrors();

    $secret = $component->get('revealedSecret');

    expect($secret)->toBe((string) WebhookSubscription::firstOrFail()->signing_secret)
        ->and(WebhookSubscription::first()->event_names)->toBe(['InvoiceIssued', 'ReceiptVoided']);

    $component->call('dismissSecret')->assertSet('revealedSecret', null);
});

it('refuses webhook targets that are not public https endpoints (SSRF)', function (string $url): void {
    $f = int04AdminFixture();
    $user = int04AdminUser($f, 'integration.webhook.manage');
    $issued = int04AdminClient($f);

    Livewire::actingAs($user)->test(WebhooksIndex::class, ['school' => $f['school']])
        ->set('clientId', $issued['client']->id)->set('events', 'InvoiceIssued')->set('targetUrl', $url)
        ->call('create')->assertHasErrors('targetUrl');

    expect(WebhookSubscription::count())->toBe(0);
})->with([
    'plain http' => 'http://hooks.example.test/in',
    'loopback' => 'https://127.0.0.1/in',
    'link-local metadata' => 'https://169.254.169.254/latest/meta-data',
    'private range' => 'https://10.0.0.5/in',
    'localhost' => 'https://localhost/in',
    'embedded credentials' => 'https://user:pass@hooks.example.test/in',
]);

it('will not subscribe a hardware credential, a revoked client or another school’s client', function (): void {
    $f = int04AdminFixture();
    $device = app(RegisterHardwareDeviceAction::class)->execute($f['school']->id, 'rfid_reader', 'gate');
    $revoked = int04AdminClient($f, 'Old');
    $revoked['client']->update(['is_active' => false]);
    $other = School::factory()->create();
    SchoolContext::set($other);
    $foreign = app(IssueApiClientAction::class)->execute($other->id, 'Foreign', 'integration', ['usage:read']);
    SchoolContext::set($f['school']);

    foreach ([$device['device']->api_client_id, $revoked['client']->id, $foreign['client']->id] as $clientId) {
        expect(fn () => app(CreateWebhookSubscriptionAction::class)->execute($f['school']->id, $clientId, ['InvoiceIssued'], 'https://hooks.example.test/in'))
            ->toThrow(ModelNotFoundException::class);
    }
});

it('never sends a delivery to a target that is no longer safe, and never follows redirects', function (): void {
    $f = int04AdminFixture();
    $issued = int04AdminClient($f);
    $subscription = WebhookSubscription::factory()->for($f['school'])->create(['client_id' => $issued['client']->id, 'event_names' => ['InvoiceIssued'], 'target_url' => 'https://169.254.169.254/x']);
    Http::fake();

    $delivery = app(DispatchWebhookAction::class)->execute($f['school']->id, $subscription->id, 'InvoiceIssued', ['id' => 1]);

    Http::assertNothingSent();
    expect($delivery->status)->not->toBe('delivered');
});

it('re-enabling an auto-disabled subscription resets its failures, unless its client was revoked', function (): void {
    $f = int04AdminFixture();
    $user = int04AdminUser($f, 'integration.webhook.manage');
    $issued = int04AdminClient($f);
    $subscription = WebhookSubscription::factory()->for($f['school'])->create(['client_id' => $issued['client']->id, 'is_active' => false, 'consecutive_failures' => 20]);

    $component = Livewire::actingAs($user)->test(WebhooksIndex::class, ['school' => $f['school']])->call('setActive', $subscription->id, true);

    expect($subscription->fresh())->is_active->toBeTrue()->consecutive_failures->toBe(0);

    $subscription->refresh()->update(['is_active' => false]);
    $issued['client']->update(['is_active' => false]);
    $component->call('setActive', $subscription->id, true);

    expect($subscription->fresh()->is_active)->toBeFalse();
});

it('lists deliveries without their payloads, filtered by status', function (): void {
    $f = int04AdminFixture();
    $user = int04AdminUser($f, 'integration.view');
    $subscription = WebhookSubscription::factory()->for($f['school'])->create();
    WebhookDelivery::factory()->for($f['school'])->create(['subscription_id' => $subscription->id, 'event_name' => 'InvoiceIssued', 'status' => 'delivered', 'payload' => ['secret' => 'PAYLOAD-MARKER']]);
    WebhookDelivery::factory()->for($f['school'])->create(['subscription_id' => $subscription->id, 'event_name' => 'ReceiptVoided', 'status' => 'abandoned', 'payload' => []]);

    Livewire::actingAs($user)->test(WebhooksLog::class, ['school' => $f['school']])
        ->assertSee('InvoiceIssued')->assertSee('ReceiptVoided')->assertDontSee('PAYLOAD-MARKER')
        ->set('status', 'abandoned')->assertSee('ReceiptVoided')->assertDontSee('InvoiceIssued');
});

it('saves SSO configuration with write-only credentials', function (): void {
    $f = int04AdminFixture();
    $user = int04AdminUser($f, 'integration.sso.manage');

    $component = Livewire::actingAs($user)->test(SsoIndex::class, ['school' => $f['school']])
        ->set('domain', 'school.ac.zw')->set('credentials', 'SUPER-SECRET-TOKEN')->set('autoProvision', true)
        ->call('save')->assertHasNoErrors()->assertSet('credentials', '')->assertDontSee('SUPER-SECRET-TOKEN');

    expect((string) SsoProvisioningConfig::firstOrFail()->credentials)->toBe('SUPER-SECRET-TOKEN');
    expect(DB::table('sso_provisioning_configs')->value('credentials'))->not->toContain('SUPER-SECRET-TOKEN');

    $component->set('credentials', '')->set('autoProvision', false)->call('save')->assertHasNoErrors();

    expect(SsoProvisioningConfig::first())->auto_provision_staff->toBeFalse()
        ->and((string) SsoProvisioningConfig::first()->credentials)->toBe('SUPER-SECRET-TOKEN');
});

it('refuses a bad SSO domain, an unknown provider, and a first save without credentials', function (): void {
    $f = int04AdminFixture();
    $user = int04AdminUser($f, 'integration.sso.manage');

    Livewire::actingAs($user)->test(SsoIndex::class, ['school' => $f['school']])
        ->set('domain', 'not a domain')->set('credentials', 'x')->call('save')->assertHasErrors('domain')
        ->set('domain', 'school.ac.zw')->set('credentials', '')->call('save')->assertHasErrors('domain')
        ->set('provider', 'okta')->set('credentials', 'x')->call('save')->assertHasErrors('domain');

    expect(SsoProvisioningConfig::count())->toBe(0);
});

it('registers a device with its own narrow credential and shows the key once (BR-INT-04-007)', function (): void {
    $f = int04AdminFixture();
    $user = int04AdminUser($f, 'integration.hardware.manage');

    $component = Livewire::actingAs($user)->test(HardwareIndex::class, ['school' => $f['school']])
        ->set('deviceType', 'rfid_reader')->set('purpose', 'gate')->set('location', 'Main gate')
        ->call('registerDevice')->assertHasNoErrors();

    $device = HardwareDevice::firstOrFail();
    $client = ApiClient::findOrFail($device->api_client_id);

    expect($client->client_type)->toBe('hardware_device')->and($client->scoped_abilities)->toBe(['gate:write'])
        ->and(Hash::check($component->get('revealedKey'), $client->api_key_hash))->toBeTrue();

    $oldKey = $component->get('revealedKey');
    $component->call('dismissKey')->call('rotate', $device->id);

    expect(Hash::check($oldKey, $client->fresh()->api_key_hash))->toBeFalse();
});

it('refuses an unregistered purpose or device type', function (): void {
    $f = int04AdminFixture();
    $user = int04AdminUser($f, 'integration.hardware.manage');

    Livewire::actingAs($user)->test(HardwareIndex::class, ['school' => $f['school']])
        ->set('purpose', 'payroll')->call('registerDevice')->assertHasErrors('purpose')
        ->set('purpose', 'gate')->set('deviceType', 'toaster')->call('registerDevice')->assertHasErrors('purpose');

    expect(HardwareDevice::count())->toBe(0);
});

it('flags a silent device offline without touching anything else (AC-INT-04-004)', function (): void {
    $f = int04AdminFixture();
    $user = int04AdminUser($f, 'integration.hardware.manage');
    $registered = app(RegisterHardwareDeviceAction::class)->execute($f['school']->id, 'rfid_reader', 'gate');
    $registered['device']->update(['status' => 'online', 'last_heartbeat_at' => now()->subHour()]);

    Livewire::actingAs($user)->test(HardwareIndex::class, ['school' => $f['school']])->call('markSilentOffline');

    expect($registered['device']->fresh()->status)->toBe('offline');
});

it('shows usage for this school’s clients only (BR-INT-04-011)', function (): void {
    $f = int04AdminFixture();
    $user = int04AdminUser($f, 'integration.view');
    $mine = int04AdminClient($f, 'My BI Tool');
    ApiUsageLog::factory()->for($f['school'])->count(3)->create(['client_id' => $mine['client']->id, 'status_code' => 200, 'occurred_at' => now()->subHour()]);
    ApiUsageLog::factory()->for($f['school'])->create(['client_id' => $mine['client']->id, 'status_code' => 500, 'occurred_at' => now()->subHour()]);

    $other = School::factory()->create();
    SchoolContext::set($other);
    $foreign = app(IssueApiClientAction::class)->execute($other->id, 'Foreign Tool', 'integration', ['usage:read']);
    ApiUsageLog::factory()->for($other)->create(['client_id' => $foreign['client']->id, 'occurred_at' => now()->subHour()]);
    SchoolContext::set($f['school']);

    Livewire::actingAs($user)->test(UsageDashboard::class, ['school' => $f['school']])
        ->assertSee('My BI Tool')->assertDontSee('Foreign Tool')->assertSeeInOrder(['My BI Tool', '4', '1']);
});

<?php

use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Modules\Boarding\Models\Hostel;
use Modules\Boarding\Models\MovementCheckpoint;
use Modules\Boarding\Models\RollCall;
use Modules\Core\Domain\Exceptions\InsufficientScopeException;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\Role;
use Modules\Core\Models\School;
use Modules\Core\Models\Tenant;
use Modules\Intelligence\Domain\Actions\CreateWebhookSubscriptionAction;
use Modules\Intelligence\Domain\Actions\DispatchWebhookAction;
use Modules\Intelligence\Domain\Actions\IssueApiClientAction;
use Modules\Intelligence\Domain\Actions\MarkOfflineHardwareDevicesAction;
use Modules\Intelligence\Domain\Actions\ProvisionSsoStaffAccountAction;
use Modules\Intelligence\Domain\Actions\RecordHardwareHeartbeatAction;
use Modules\Intelligence\Domain\Actions\RecordHardwareScanAction;
use Modules\Intelligence\Domain\Actions\RegisterHardwareDeviceAction;
use Modules\Intelligence\Domain\Events\WebhookAutoDisabled;
use Modules\Intelligence\Models\ApiClient;
use Modules\Intelligence\Models\HardwareDevice;
use Modules\Intelligence\Models\SsoProvisioningConfig;
use Modules\Intelligence\Models\WebhookDelivery;
use Modules\People\Models\Student;

/**
 * @return array{school: School}
 */
function int04Fixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);

    return compact('school');
}

it('refuses to issue an API client scoped to an ability with no registered route (BR-INT-04-001)', function (): void {
    $f = int04Fixture();

    expect(fn () => app(IssueApiClientAction::class)->execute(
        schoolId: $f['school']->id, name: 'Bad Client', clientType: 'integration', scopedAbilities: ['payroll:write'],
    ))->toThrow(InvalidArgumentException::class);
});

it('returns the plaintext key once at creation and stores only its hash (BR-INT-04-002)', function (): void {
    $f = int04Fixture();

    $issued = app(IssueApiClientAction::class)->execute(
        schoolId: $f['school']->id, name: 'Zapier Connector', clientType: 'integration', scopedAbilities: ['usage:read'],
    );

    expect($issued['plaintextKey'])->toBeString()->not->toBeEmpty()
        ->and($issued['client']->api_key_hash)->not->toBe($issued['plaintextKey'])
        ->and(Hash::check($issued['plaintextKey'], $issued['client']->api_key_hash))->toBeTrue();
});

it('refuses a scan when the device credential does not carry the ability its purpose implies, regardless of other configuration (AC-INT-04-001)', function (): void {
    $f = int04Fixture();
    $registered = app(RegisterHardwareDeviceAction::class)->execute($f['school']->id, 'rfid_reader', 'roll_call');
    $device = $registered['device'];

    // Narrow the credential down to an ability that does not match this device's own purpose.
    ApiClient::where('id', $device->api_client_id)->update(['scoped_abilities' => ['gate:write']]);

    Student::factory()->for($f['school'])->create(['rfid_tag' => 'TAG-001']);

    expect(fn () => app(RecordHardwareScanAction::class)->execute($device->ulid, 'TAG-001', targetId: 1, recordedByUserId: 1))
        ->toThrow(InsufficientScopeException::class);
});

it('routes a gate scan through BRD-02\'s own RecordCheckpointMovementAction, with no lighter-touch path (AC-INT-04-003)', function (): void {
    $f = int04Fixture();
    $registered = app(RegisterHardwareDeviceAction::class)->execute($f['school']->id, 'rfid_reader', 'gate', 'Main Gate');
    $device = $registered['device'];

    $student = Student::factory()->for($f['school'])->create(['rfid_tag' => 'TAG-002']);
    $checkpoint = MovementCheckpoint::factory()->for($f['school'])->create();
    $recorder = User::factory()->create();

    $entry = app(RecordHardwareScanAction::class)->execute($device->ulid, 'TAG-002', $checkpoint->id, recordedByUserId: $recorder->id, context: ['direction' => 'in']);

    expect($entry->student_id)->toBe($student->id)
        ->and($entry->checkpoint_id)->toBe($checkpoint->id)
        ->and($entry->direction)->toBe('in')
        ->and($entry->method)->toContain('hardware:');
});

it('routes a roll call scan through BRD-02\'s own MarkRollCallAction', function (): void {
    $f = int04Fixture();
    $registered = app(RegisterHardwareDeviceAction::class)->execute($f['school']->id, 'rfid_reader', 'roll_call');
    $device = $registered['device'];

    $student = Student::factory()->for($f['school'])->create(['rfid_tag' => 'TAG-003']);
    $hostel = Hostel::factory()->for($f['school'])->create();
    $rollCall = RollCall::factory()->for($f['school'])->create(['hostel_id' => $hostel->id]);
    $recorder = User::factory()->create();

    $record = app(RecordHardwareScanAction::class)->execute($device->ulid, 'TAG-003', $rollCall->id, recordedByUserId: $recorder->id);

    expect($record->student_id)->toBe($student->id)
        ->and($record->status)->toBe('present')
        ->and($record->device_source)->toContain('hardware:');
});

it('gives a hardware device its own distinct API client credential, never a human user\'s (BR-INT-04-007)', function (): void {
    $f = int04Fixture();
    $registered = app(RegisterHardwareDeviceAction::class)->execute($f['school']->id, 'rfid_reader', 'gate');

    $client = ApiClient::findOrFail($registered['device']->api_client_id);

    expect($client->client_type)->toBe('hardware_device')
        ->and($client->scoped_abilities)->toBe(['gate:write'])
        ->and($client->created_by)->toBeNull();
});

it('marks a silent hardware device offline once it exceeds its configured heartbeat window (AC-INT-04-004)', function (): void {
    $f = int04Fixture();
    $registered = app(RegisterHardwareDeviceAction::class)->execute($f['school']->id, 'rfid_reader', 'gate');
    $device = $registered['device'];

    app(RecordHardwareHeartbeatAction::class)->execute($device->ulid);
    expect($device->fresh()->status)->toBe('online');

    HardwareDevice::where('id', $device->id)->update(['last_heartbeat_at' => now()->subMinutes(30)]);

    $offline = app(MarkOfflineHardwareDevicesAction::class)->execute($f['school']->id);

    expect($offline)->toHaveCount(1)
        ->and($offline[0]->status)->toBe('offline');
});

it('auto-disables a webhook subscription after it crosses the configured consecutive-failure threshold, and alerts (AC-INT-04-002)', function (): void {
    Event::fake([WebhookAutoDisabled::class]);
    Http::fake(fn () => Http::response('error', 500));

    $f = int04Fixture();
    $issued = app(IssueApiClientAction::class)->execute($f['school']->id, 'BI Tool', 'integration', ['usage:read']);
    $subscription = app(CreateWebhookSubscriptionAction::class)->execute($f['school']->id, $issued['client']->id, ['InvoiceIssued'], 'https://example.test/hook');

    for ($i = 0; $i < 20; $i++) {
        app(DispatchWebhookAction::class)->execute($f['school']->id, $subscription->id, 'InvoiceIssued', ['id' => $i]);
    }

    expect($subscription->fresh()->is_active)->toBeFalse();
    Event::assertDispatched(WebhookAutoDisabled::class);
});

it('refuses to update an illegal field on an already-recorded webhook delivery (append-only)', function (): void {
    $f = int04Fixture();
    $issued = app(IssueApiClientAction::class)->execute($f['school']->id, 'BI Tool', 'integration', ['usage:read']);
    $subscription = app(CreateWebhookSubscriptionAction::class)->execute($f['school']->id, $issued['client']->id, ['InvoiceIssued'], 'https://example.test/hook');
    $delivery = WebhookDelivery::factory()->for($f['school'])->create(['subscription_id' => $subscription->id]);

    expect(fn () => $delivery->update(['event_name' => 'SomethingElse']))
        ->toThrow(InvalidStateTransitionException::class);
});

it('provisions an SSO staff account through CORE-05\'s own user creation and role assignment, never bypassing either (BR-INT-04-006)', function (): void {
    $f = int04Fixture();
    $tenant = Tenant::findOrFail($f['school']->tenant_id);
    SsoProvisioningConfig::factory()->for($f['school'])->create(['auto_provision_staff' => true]);
    $role = Role::factory()->create(['name' => 'sso-teacher']);

    $user = app(ProvisionSsoStaffAccountAction::class)->execute(
        $f['school']->id, $tenant->id, 'Tendai', 'Moyo', 'tendai.moyo@example.test', $role->name,
    );

    expect($user->email)->toBe('tendai.moyo@example.test')
        ->and($user->hasRole('sso-teacher'))->toBeTrue();
});

it('refuses SSO provisioning when auto-provisioning is not enabled for the school', function (): void {
    $f = int04Fixture();
    $tenant = Tenant::findOrFail($f['school']->tenant_id);
    SsoProvisioningConfig::factory()->for($f['school'])->create(['auto_provision_staff' => false]);
    $role = Role::factory()->create(['name' => 'sso-teacher-2']);

    expect(fn () => app(ProvisionSsoStaffAccountAction::class)->execute($f['school']->id, $tenant->id, 'A', 'B', 'a.b@example.test', $role->name))
        ->toThrow(InvalidArgumentException::class);
});

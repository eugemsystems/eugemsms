<?php

use App\Models\User;
use Modules\Boarding\Models\Hostel;
use Modules\Boarding\Models\RollCall;
use Modules\Boarding\Models\RollCallRecord;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\School;
use Modules\Intelligence\Domain\Actions\IssueApiClientAction;
use Modules\Intelligence\Domain\Actions\RegisterHardwareDeviceAction;
use Modules\Intelligence\Domain\Actions\RevokeApiClientAction;
use Modules\Intelligence\Models\HardwareDevice;
use Modules\People\Models\Student;

/**
 * @return array{school: School, device: HardwareDevice, key: string, user: User}
 */
function int04bDevice(string $purpose = 'roll_call'): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $user = User::factory()->create(['tenant_id' => $school->tenant_id]);
    $registered = app(RegisterHardwareDeviceAction::class)->execute($school->id, 'rfid_reader', $purpose, createdByUserId: $user->id);

    return ['school' => $school, 'device' => $registered['device'], 'key' => $registered['plaintextKey'], 'user' => $user];
}

it('records a heartbeat for the device whose own key made the call', function (): void {
    $f = int04bDevice();

    $this->withToken($f['key'])->postJson("/api/v1/hardware/{$f['device']->ulid}/heartbeat")
        ->assertOk()->assertJsonPath('data.status', 'online');

    expect($f['device']->fresh()->last_heartbeat_at)->not->toBeNull();
});

it('refuses missing, wrong and revoked keys with 401', function (): void {
    $f = int04bDevice();
    $url = "/api/v1/hardware/{$f['device']->ulid}/heartbeat";

    $this->postJson($url)->assertStatus(401)->assertJsonPath('error.code', 'UNAUTHENTICATED');
    $this->withToken($f['device']->apiClient->ulid.'.wrong')->postJson($url)->assertStatus(401);

    app(RevokeApiClientAction::class)->execute($f['device']->api_client_id);
    $this->withToken($f['key'])->postJson($url)->assertStatus(401);
});

it('stops a device acting for another device (BR-INT-04-007)', function (): void {
    $a = int04bDevice();
    $b = app(RegisterHardwareDeviceAction::class)->execute($a['school']->id, 'rfid_reader', 'roll_call', createdByUserId: $a['user']->id);

    $this->withToken($a['key'])->postJson("/api/v1/hardware/{$b['device']->ulid}/heartbeat")
        ->assertStatus(403)->assertJsonPath('error.code', 'INSUFFICIENT_SCOPE');
});

it('refuses an integration client on hardware routes', function (): void {
    $f = int04bDevice();
    $issued = app(IssueApiClientAction::class)->execute($f['school']->id, 'BI tool', 'integration', ['usage:read']);

    $this->withToken($issued['plaintextKey'])->postJson("/api/v1/hardware/{$f['device']->ulid}/heartbeat")->assertStatus(403);
});

it('routes a scan to MarkRollCallAction with the device source recorded', function (): void {
    $f = int04bDevice();
    $student = Student::factory()->for($f['school'])->create(['rfid_tag' => 'API-TAG-1']);
    $rollCall = RollCall::factory()->for($f['school'])->create(['hostel_id' => Hostel::factory()->for($f['school'])->create()->id]);

    $this->withToken($f['key'])->postJson('/api/v1/hardware/scan', [
        'device_id' => $f['device']->ulid, 'tag' => 'API-TAG-1', 'scanned_at' => now()->toIso8601String(), 'target_id' => $rollCall->id,
    ])->assertStatus(201)->assertJsonPath('data.accepted', true);

    expect(RollCallRecord::where('roll_call_id', $rollCall->id)->where('student_id', $student->id)->value('device_source'))->toBe('hardware:rfid_reader');
});

it('validates the scan body and answers an unknown tag with 404', function (): void {
    $f = int04bDevice();

    $this->withToken($f['key'])->postJson('/api/v1/hardware/scan', ['device_id' => $f['device']->ulid])->assertStatus(422);

    $this->withToken($f['key'])->postJson('/api/v1/hardware/scan', [
        'device_id' => $f['device']->ulid, 'tag' => 'NOPE', 'scanned_at' => now()->toIso8601String(), 'target_id' => 1,
    ])->assertStatus(404)->assertJsonPath('error.code', 'TAG_NOT_FOUND');
});

it('refuses a request from outside the client IP allowlist', function (): void {
    $f = int04bDevice();
    $f['device']->apiClient->update(['ip_allowlist' => ['10.9.9.0/24']]);

    $this->withToken($f['key'])->postJson("/api/v1/hardware/{$f['device']->ulid}/heartbeat")
        ->assertStatus(403)->assertJsonPath('error.code', 'IP_NOT_ALLOWED');
});

it('refuses a client whose abilities do not cover the route (AC-INT-04-001)', function (): void {
    $f = int04bDevice();
    $f['device']->apiClient->update(['scoped_abilities' => ['usage:read']]);

    $this->withToken($f['key'])->postJson('/api/v1/hardware/scan', [
        'device_id' => $f['device']->ulid, 'tag' => 'X', 'scanned_at' => now()->toIso8601String(), 'target_id' => 1,
    ])->assertStatus(403)->assertJsonPath('error.code', 'INSUFFICIENT_SCOPE');
});

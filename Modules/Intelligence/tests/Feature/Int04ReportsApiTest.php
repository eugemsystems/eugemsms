<?php

use App\Models\User;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;
use Modules\Intelligence\Domain\Actions\CreateCustomReportAction;
use Modules\Intelligence\Domain\Actions\IssueApiClientAction;
use Modules\Intelligence\Domain\DataObjects\CreateCustomReportData;
use Modules\Intelligence\Models\ApiClient;
use Modules\People\Models\Student;

/**
 * Book J INT-01 §5 — `/api/v1/reports/*` (`serp.api-client:reports:read`),
 * closing the gap `routes/intelligence.php`'s own docblock used to list.
 * A third-party key acts as its own `created_by` user: a report is
 * runnable/exportable through the API only if that user could already
 * run it as a human (author or share recipient), re-evaluated against
 * their own live permissions every time, same as `RunSavedReportAction`.
 *
 * @return array{school: School, user: User, key: string, client: ApiClient}
 */
function int04cReportsFixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $user = User::factory()->create();
    $user->schools()->attach($school, ['status' => 'active']);

    $permission = Permission::firstOrCreate(['name' => 'people.student.view'], ['guard_name' => 'web', 'module_code' => 'PEOPLE', 'resource' => 'student', 'action' => 'view']);
    app(UpdateUserPermissionsAction::class)->execute(new UserPermissionData(
        userId: $user->id, schoolId: $school->id, grants: [new PermissionGrantData($permission->id, PermissionScope::School)],
    ));

    $issued = app(IssueApiClientAction::class)->execute(
        schoolId: $school->id, name: 'BI Tool', clientType: 'integration', scopedAbilities: ['reports:read'], createdByUserId: $user->id,
    );

    return ['school' => $school, 'user' => $user, 'key' => $issued['plaintextKey'], 'client' => $issued['client']];
}

it('refuses a reports call from a key with no reports:read ability', function (): void {
    $f = int04cReportsFixture();
    ApiClient::where('id', $f['client']->id)->update(['scoped_abilities' => ['usage:read']]);

    $this->withToken($f['key'])->getJson('/api/v1/reports/entities')->assertStatus(403);
});

it('lists field-permission-filtered entities for the key\'s own registering user', function (): void {
    $f = int04cReportsFixture();

    $response = $this->withToken($f['key'])->getJson('/api/v1/reports/entities')->assertOk();

    expect($response->json('data.entities.student.fields'))->not->toBeEmpty()
        ->and(collect($response->json('data.entities.student.fields'))->pluck('field_key'))->toContain('first_name');
});

it('runs a saved report through the API as the key\'s own registering user', function (): void {
    $f = int04cReportsFixture();
    Student::factory()->for($f['school'])->create(['first_name' => 'Tendai']);

    $report = app(CreateCustomReportAction::class)->execute(new CreateCustomReportData(
        schoolId: $f['school']->id, name: 'Roll', primaryEntityKey: 'student',
        selectedFields: [['entity' => 'student', 'field' => 'first_name']],
        createdByUserId: $f['user']->id,
    ));

    $this->withToken($f['key'])->postJson("/api/v1/reports/{$report->ulid}/run")
        ->assertOk()
        ->assertJsonPath('data.row_count', 1)
        ->assertJsonPath('data.rows.0.first_name', 'Tendai');
});

it('refuses to run a report belonging to another school\'s client, or a report the registering user may not run', function (): void {
    $f = int04cReportsFixture();
    $other = int04cReportsFixture();

    $theirs = app(CreateCustomReportAction::class)->execute(new CreateCustomReportData(
        schoolId: $other['school']->id, name: 'Theirs', primaryEntityKey: 'student',
        selectedFields: [['entity' => 'student', 'field' => 'first_name']],
        createdByUserId: $other['user']->id,
    ));

    // Wrong school entirely.
    $this->withToken($f['key'])->postJson("/api/v1/reports/{$theirs->ulid}/run")->assertStatus(404);

    // Same school, but not authored by (or shared with) this key's own registering user.
    SchoolContext::set($f['school']);
    $stranger = User::factory()->create();
    $strangerReport = app(CreateCustomReportAction::class)->execute(new CreateCustomReportData(
        schoolId: $f['school']->id, name: 'Not mine', primaryEntityKey: 'student',
        selectedFields: [['entity' => 'student', 'field' => 'first_name']],
        createdByUserId: $stranger->id,
    ));

    $this->withToken($f['key'])->postJson("/api/v1/reports/{$strangerReport->ulid}/run")->assertStatus(403);
});

it('exports a run report as csv, excel and pdf', function (): void {
    $f = int04cReportsFixture();
    Student::factory()->for($f['school'])->create(['first_name' => 'Rudo']);

    $report = app(CreateCustomReportAction::class)->execute(new CreateCustomReportData(
        schoolId: $f['school']->id, name: 'Roll Call', primaryEntityKey: 'student',
        selectedFields: [['entity' => 'student', 'field' => 'first_name']],
        createdByUserId: $f['user']->id,
    ));

    $csv = $this->withToken($f['key'])->get("/api/v1/reports/{$report->ulid}/export?format=csv")->assertOk();
    expect($csv->headers->get('Content-Type'))->toContain('csv');

    $excel = $this->withToken($f['key'])->get("/api/v1/reports/{$report->ulid}/export?format=excel")->assertOk();
    expect($excel->headers->get('Content-Type'))->toContain('spreadsheetml');

    $pdf = $this->withToken($f['key'])->get("/api/v1/reports/{$report->ulid}/export?format=pdf")->assertOk();
    expect($pdf->headers->get('Content-Type'))->toBe('application/pdf');

    $this->withToken($f['key'])->get("/api/v1/reports/{$report->ulid}/export?format=bogus")->assertStatus(422);
});

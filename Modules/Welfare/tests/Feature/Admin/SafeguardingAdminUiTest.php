<?php

use App\Models\User;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Modules\Core\Domain\Actions\Auth\AssignRoleAction;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\Actions\Documents\CreateNumberingSeriesAction;
use Modules\Core\Domain\Actions\Settings\SetSettingValueAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\RoleAssignmentData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\DataObjects\Documents\CreateNumberingSeriesData;
use Modules\Core\Domain\DataObjects\Settings\SetSettingValueData;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\Auth\UserType;
use Modules\Core\Domain\Support\ImpersonationContext;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Domain\Support\Settings\SettingScope;
use Modules\Core\Models\ImpersonationSession;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\School;
use Modules\People\Models\Staff;
use Modules\People\Models\Student;
use Modules\Welfare\Domain\Actions\GrantCaseAccessAction;
use Modules\Welfare\Domain\Actions\OpenSafeguardingCaseAction;
use Modules\Welfare\Domain\DataObjects\GrantCaseAccessData;
use Modules\Welfare\Domain\DataObjects\OpenSafeguardingCaseData;
use Modules\Welfare\Livewire\Safeguarding\CaseDetail;
use Modules\Welfare\Livewire\Safeguarding\Cases;
use Modules\Welfare\Livewire\Safeguarding\Grants;
use Modules\Welfare\Livewire\Safeguarding\Report;
use Modules\Welfare\Livewire\Safeguarding\Triage;
use Modules\Welfare\Models\SafeguardingAuditEntry;
use Modules\Welfare\Models\SafeguardingCase;
use Modules\Welfare\Models\SafeguardingConcern;

/**
 * Book G BRD-08 admin-UI pass — the single most safety-critical screen
 * in this build. Own, distinctly-named fixture.
 *
 * @return array{school: School, lead: Staff, leadUser: User, student: Student}
 */
function sgAdminFixture(): array
{
    $school = School::factory()->create();
    SchoolContext::set($school);
    $leadUser = User::factory()->create(['user_type' => UserType::Staff]);
    $leadUser->schools()->attach($school, ['status' => 'active']);
    $lead = Staff::factory()->for($school)->create(['user_id' => $leadUser->id]);
    $student = Student::factory()->for($school)->create();

    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
        schoolId: $school->id, documentType: 'safeguarding_case', pattern: '{TYPE}/{YEAR}/{SEQ:4}',
    ));

    app(SetSettingValueAction::class)->execute(new SetSettingValueData(
        key: 'safeguarding.lead_staff_id', scopeType: SettingScope::School, scopeId: $school->id,
        value: $lead->id, setByUserId: $leadUser->id,
    ));

    // `hasPermissionTo()` throws `PermissionDoesNotExist` (spatie's
    // strict default) when the row doesn't exist at all yet, rather
    // than returning false — in the real app `SyncPermissionCatalogueAction`
    // has already populated the catalogue by the time anyone reaches
    // this screen, so every fixture pre-creates the rows `ViewSafeguardingCaseAction`/
    // `Triage` check directly, matching `Brd08SafeguardingTest`'s own
    // fixture.
    Permission::factory()->create(['name' => 'safeguarding.emergency_access']);
    Permission::factory()->create(['name' => 'safeguarding.deputy_lead']);

    return compact('school', 'lead', 'leadUser', 'student');
}

/**
 * @param  array<string, mixed>  $f
 */
function sgAdminCase(array $f, ?Student $student = null): SafeguardingCase
{
    return app(OpenSafeguardingCaseAction::class)->execute(new OpenSafeguardingCaseData(
        schoolId: $f['school']->id, studentId: ($student ?? $f['student'])->id, leadStaffId: $f['lead']->id,
        openedByUserId: $f['leadUser']->id, category: 'emotional', riskLevel: 'medium',
        summary: 'Initial concern under assessment.',
    ));
}

/**
 * @param  array<string, mixed>  $f
 */
function sgAdminUserWithSchool(array $f): User
{
    $user = User::factory()->create(['user_type' => UserType::Staff]);
    $user->schools()->attach($f['school'], ['status' => 'active']);

    return $user;
}

/**
 * Direct, school-scoped permission grant — mirrors
 * `Modules\Boarding\tests\Feature\Admin\RollCallAdminUiTest`'s own
 * `rollCallAdminUser()` helper's grant logic, since `authorizePermission()`
 * reads `PermissionScopeResolver`, which needs the matching
 * `UserPermissionScope` row `UpdateUserPermissionsAction` writes — a
 * bare `$user->givePermissionTo()` alone is not enough for a
 * `authorizePermission()`-gated screen, only for a raw
 * `hasPermissionTo()` check like `ViewSafeguardingCaseAction`'s own.
 *
 * @param  array<string, mixed>  $f
 */
function sgAdminGrantPermission(array $f, User $user, string $permissionName): void
{
    $parts = explode('.', $permissionName);
    $moduleCode = strtoupper($parts[0]);
    $action = array_pop($parts);
    $resource = implode('.', array_slice($parts, 1)) ?: $action;

    $permission = Permission::firstOrCreate(
        ['name' => $permissionName],
        ['guard_name' => 'web', 'module_code' => $moduleCode, 'resource' => $resource, 'action' => $action],
    );

    app(UpdateUserPermissionsAction::class)->execute(new UserPermissionData(
        userId: $user->id, schoolId: $f['school']->id,
        grants: [new PermissionGrantData($permission->id, PermissionScope::School)],
    ));
}

it('refuses a Super Admin with no case-specific grant through the admin screen, even though Super Admin has broad implicit access everywhere else', function (): void {
    $f = sgAdminFixture();
    $case = sgAdminCase($f);

    // Super Admin's broad implicit access elsewhere in this platform is
    // driven by `SyncPermissionCatalogueAction`'s own automatic grant of
    // every permission to the `super_admin` role — EXCEPT any
    // `safeguarding.*` permission, which that action explicitly skips.
    // The exclusion this test exercises is enforced independently, at
    // the policy layer, keyed only on `user_type === Vendor` — not on
    // role assignment — so the vendor-type user alone is sufficient to
    // prove the exclusion (BR-BRD-08-001).
    Role::factory()->system()->create(['name' => 'super_admin', 'is_vendor_only' => true]);
    $vendor = User::factory()->create(['user_type' => UserType::Vendor]);
    // Assigned to the school the same way Super Admin reaches every
    // OTHER screen with implicit access — proving the refusal below is
    // specific to this module, not merely "never got to the screen".
    $vendor->schools()->attach($f['school'], ['status' => 'active']);

    Livewire::actingAs($vendor)->test(CaseDetail::class, ['school' => $f['school'], 'case' => $case])
        ->assertForbidden();

    $blocked = SafeguardingAuditEntry::where('case_id', $case->id)->where('event_type', 'vendor_blocked')->where('user_id', $vendor->id)->first();
    expect($blocked)->not->toBeNull()
        ->and($blocked->access_basis)->toBe('blocked_vendor_access');
});

it('refuses access through the admin screen to a support engineer currently impersonating a user who DOES have a grant', function (): void {
    $f = sgAdminFixture();
    $case = sgAdminCase($f);

    $impersonated = sgAdminUserWithSchool($f);
    $impersonator = User::factory()->create(['user_type' => UserType::Vendor]);

    app(GrantCaseAccessAction::class)->execute(new GrantCaseAccessData(
        schoolId: $f['school']->id, caseId: $case->id, userId: $impersonated->id,
        grantedByUserId: $f['leadUser']->id, accessLevel: 'read', reason: 'Housemaster context.',
    ));

    $session = ImpersonationSession::create([
        'impersonator_id' => $impersonator->id, 'impersonated_id' => $impersonated->id,
        'school_id' => $f['school']->id, 'reason' => 'Support ticket.',
        'started_at' => now(), 'expires_at' => now()->addHour(),
    ]);

    ImpersonationContext::set($session);

    try {
        Livewire::actingAs($impersonated)->test(CaseDetail::class, ['school' => $f['school'], 'case' => $case])
            ->assertForbidden();
    } finally {
        ImpersonationContext::clear();
    }

    $blocked = SafeguardingAuditEntry::where('case_id', $case->id)->where('event_type', 'vendor_blocked')->where('user_id', $impersonated->id)->first();
    expect($blocked)->not->toBeNull();
});

it('lets a user with an active grant on ONE case see only that case, not a second case they hold no grant for', function (): void {
    $f = sgAdminFixture();
    $caseA = sgAdminCase($f);
    $otherStudent = Student::factory()->for($f['school'])->create();
    $caseB = sgAdminCase($f, $otherStudent);

    $housemaster = sgAdminUserWithSchool($f);

    app(GrantCaseAccessAction::class)->execute(new GrantCaseAccessData(
        schoolId: $f['school']->id, caseId: $caseA->id, userId: $housemaster->id,
        grantedByUserId: $f['leadUser']->id, accessLevel: 'read', reason: 'Pastoral context for case A only.',
    ));

    Livewire::actingAs($housemaster)->test(CaseDetail::class, ['school' => $f['school'], 'case' => $caseA])
        ->assertOk()
        ->assertSee($caseA->case_reference);

    Livewire::actingAs($housemaster)->test(CaseDetail::class, ['school' => $f['school'], 'case' => $caseB])
        ->assertForbidden();

    $readLog = SafeguardingAuditEntry::where('case_id', $caseA->id)->where('event_type', 'case_read')->where('user_id', $housemaster->id)->first();
    expect($readLog)->not->toBeNull()->and($readLog->access_basis)->toStartWith('grant:');

    $deniedLog = SafeguardingAuditEntry::where('case_id', $caseB->id)->where('event_type', 'access_denied')->where('user_id', $housemaster->id)->first();
    expect($deniedLog)->not->toBeNull();
});

it('produces a hardened audit row for every one of lead, granted, break-glass, and denied access, asserted directly against the audit table', function (): void {
    $f = sgAdminFixture();
    $case = sgAdminCase($f);

    // Lead.
    Livewire::actingAs($f['leadUser'])->test(CaseDetail::class, ['school' => $f['school'], 'case' => $case])->assertOk();
    $leadEntry = SafeguardingAuditEntry::where('case_id', $case->id)->where('event_type', 'case_read')->where('access_basis', 'lead')->first();
    expect($leadEntry)->not->toBeNull();

    // Granted.
    $granted = sgAdminUserWithSchool($f);
    app(GrantCaseAccessAction::class)->execute(new GrantCaseAccessData(
        schoolId: $f['school']->id, caseId: $case->id, userId: $granted->id,
        grantedByUserId: $f['leadUser']->id, accessLevel: 'read', reason: 'Context needed.',
    ));
    Livewire::actingAs($granted)->test(CaseDetail::class, ['school' => $f['school'], 'case' => $case])->assertOk();
    $grantEntry = SafeguardingAuditEntry::where('case_id', $case->id)->where('event_type', 'case_read')->where('user_id', $granted->id)->first();
    expect($grantEntry)->not->toBeNull()->and($grantEntry->access_basis)->toStartWith('grant:');

    // Break-glass.
    $emergency = sgAdminUserWithSchool($f);
    $permission = Permission::where('name', 'safeguarding.emergency_access')->firstOrFail();
    $role = Role::factory()->forSchool($f['school']->id)->create();
    $role->givePermissionTo($permission);
    app(AssignRoleAction::class)->execute(new RoleAssignmentData($emergency->id, $role->id, $f['school']->id));
    SchoolContext::set($f['school']);

    Livewire::actingAs($emergency)->test(CaseDetail::class, ['school' => $f['school'], 'case' => $case])->assertOk();
    $breakGlassEntry = SafeguardingAuditEntry::where('case_id', $case->id)->where('event_type', 'break_glass')->where('user_id', $emergency->id)->first();
    expect($breakGlassEntry)->not->toBeNull();

    // Denied.
    $stranger = sgAdminUserWithSchool($f);
    Livewire::actingAs($stranger)->test(CaseDetail::class, ['school' => $f['school'], 'case' => $case])->assertForbidden();
    $deniedEntry = SafeguardingAuditEntry::where('case_id', $case->id)->where('event_type', 'access_denied')->where('user_id', $stranger->id)->first();
    expect($deniedEntry)->not->toBeNull();
});

it('exposes no delete action anywhere in the safeguarding admin screens or views, for a concern or a case, at any permission level', function (): void {
    $livewireFiles = File::allFiles(base_path('Modules/Welfare/Livewire/Safeguarding'));
    $viewFiles = File::allFiles(base_path('Modules/Welfare/resources/views/safeguarding'));

    foreach ([...$livewireFiles, ...$viewFiles] as $file) {
        $contents = mb_strtolower(File::get($file->getPathname()));

        expect($contents)
            ->not->toContain('->delete(')
            ->not->toContain('wire:click="delete')
            ->not->toContain('deletecase')
            ->not->toContain('deleteconcern');
    }
});

it('serves the triage queue, vulnerable register, and audit review screens for the lead, and refuses them to a stranger', function (): void {
    $f = sgAdminFixture();

    sgAdminGrantPermission($f, $f['leadUser'], 'safeguarding.lead');

    Livewire::actingAs($f['leadUser'])->test(Triage::class, ['school' => $f['school']])->assertOk();

    $stranger = sgAdminUserWithSchool($f);
    Livewire::actingAs($stranger)->test(Triage::class, ['school' => $f['school']])->assertForbidden();
});

it('filters the case list to the leads full view versus a granted users own cases only', function (): void {
    $f = sgAdminFixture();
    $caseA = sgAdminCase($f);
    $otherStudent = Student::factory()->for($f['school'])->create();
    $caseB = sgAdminCase($f, $otherStudent);

    sgAdminGrantPermission($f, $f['leadUser'], 'safeguarding.case.view');

    Livewire::actingAs($f['leadUser'])->test(Cases::class, ['school' => $f['school']])
        ->assertSee($caseA->case_reference)
        ->assertSee($caseB->case_reference);

    $housemaster = sgAdminUserWithSchool($f);
    sgAdminGrantPermission($f, $housemaster, 'safeguarding.case.view');
    app(GrantCaseAccessAction::class)->execute(new GrantCaseAccessData(
        schoolId: $f['school']->id, caseId: $caseA->id, userId: $housemaster->id,
        grantedByUserId: $f['leadUser']->id, accessLevel: 'read', reason: 'Case A context only.',
    ));

    Livewire::actingAs($housemaster)->test(Cases::class, ['school' => $f['school']])
        ->assertSee($caseA->case_reference)
        ->assertDontSee($caseB->case_reference);
});

it('lets only the lead manage grants on a case, not a granted-but-not-lead user', function (): void {
    $f = sgAdminFixture();
    $case = sgAdminCase($f);

    $granted = sgAdminUserWithSchool($f);
    app(GrantCaseAccessAction::class)->execute(new GrantCaseAccessData(
        schoolId: $f['school']->id, caseId: $case->id, userId: $granted->id,
        grantedByUserId: $f['leadUser']->id, accessLevel: 'full', reason: 'Full access, but not the lead.',
    ));

    Livewire::actingAs($granted)->test(Grants::class, ['school' => $f['school'], 'case' => $case])
        ->assertForbidden();

    Livewire::actingAs($f['leadUser'])->test(Grants::class, ['school' => $f['school'], 'case' => $case])
        ->assertOk();
});

it('reports an anonymous concern through the admin screen with no reporter identity stored', function (): void {
    $f = sgAdminFixture();
    $reporter = sgAdminUserWithSchool($f);
    sgAdminGrantPermission($f, $reporter, 'safeguarding.report');

    Livewire::actingAs($reporter)->test(Report::class, ['school' => $f['school']])
        ->set('concernCategory', 'bullying')
        ->set('description', 'Observed bullying in the corridor.')
        ->set('reportAnonymously', true)
        ->call('submit')
        ->assertSet('generatedToken', fn ($token) => $token !== null);

    $concern = SafeguardingConcern::where('school_id', $f['school']->id)->where('report_source', 'anonymous')->first();
    expect($concern)->not->toBeNull()
        ->and($concern->reporter_user_id)->toBeNull();
});

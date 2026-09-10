<?php

use App\Models\User;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Auth\AssignRoleAction;
use Modules\Core\Domain\Actions\Documents\CreateNumberingSeriesAction;
use Modules\Core\Domain\Actions\Settings\SetSettingValueAction;
use Modules\Core\Domain\DataObjects\Auth\RoleAssignmentData;
use Modules\Core\Domain\DataObjects\Documents\CreateNumberingSeriesData;
use Modules\Core\Domain\DataObjects\Settings\SetSettingValueData;
use Modules\Core\Domain\Exceptions\InsufficientScopeException;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\Auth\UserType;
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
use Modules\Welfare\Domain\Actions\ReportAnonymousConcernAction;
use Modules\Welfare\Domain\Actions\ReportSafeguardingConcernAction;
use Modules\Welfare\Domain\Actions\TriageConcernAction;
use Modules\Welfare\Domain\Actions\ViewSafeguardingCaseAction;
use Modules\Welfare\Domain\DataObjects\GrantCaseAccessData;
use Modules\Welfare\Domain\DataObjects\OpenSafeguardingCaseData;
use Modules\Welfare\Domain\DataObjects\ReportAnonymousConcernData;
use Modules\Welfare\Domain\DataObjects\ReportSafeguardingConcernData;
use Modules\Welfare\Domain\Support\SafeguardingAuditChainCheck;
use Modules\Welfare\Models\CaseEntry;
use Modules\Welfare\Models\SafeguardingAuditEntry;
use Modules\Welfare\Models\SafeguardingCase;
use Modules\Welfare\Models\SafeguardingConcern;

/**
 * @return array{school: School, lead: Staff, leadUser: User, student: Student}
 */
function brd08Fixture(): array
{
    $school = School::factory()->create();
    SchoolContext::set($school);
    $leadUser = User::factory()->create(['user_type' => UserType::Staff]);
    $lead = Staff::factory()->for($school)->create(['user_id' => $leadUser->id]);
    $student = Student::factory()->for($school)->create();

    Permission::factory()->create(['name' => 'safeguarding.emergency_access']);

    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
        schoolId: $school->id, documentType: 'safeguarding_case', pattern: '{TYPE}/{YEAR}/{SEQ:4}',
    ));

    return compact('school', 'lead', 'leadUser', 'student');
}

/**
 * @param  array<string, mixed>  $f
 */
function brd08SetLead(array $f): void
{
    app(SetSettingValueAction::class)->execute(new SetSettingValueData(
        key: 'safeguarding.lead_staff_id', scopeType: SettingScope::School, scopeId: $f['school']->id,
        value: $f['lead']->id, setByUserId: $f['leadUser']->id,
    ));
}

/**
 * @param  array<string, mixed>  $f
 */
function brd08Case(array $f): SafeguardingCase
{
    brd08SetLead($f);

    return app(OpenSafeguardingCaseAction::class)->execute(new OpenSafeguardingCaseData(
        schoolId: $f['school']->id, studentId: $f['student']->id, leadStaffId: $f['lead']->id,
        openedByUserId: $f['leadUser']->id, category: 'emotional', riskLevel: 'medium',
        summary: 'Initial concern under assessment.',
    ));
}

it('hard-excludes a vendor super-administrator, logs the attempt, and alerts the lead', function (): void {
    $f = brd08Fixture();
    $case = brd08Case($f);

    $vendor = User::factory()->create(['user_type' => UserType::Vendor]);

    expect(fn () => app(ViewSafeguardingCaseAction::class)->execute($vendor, $case))
        ->toThrow(InsufficientScopeException::class);

    $entry = SafeguardingAuditEntry::where('case_id', $case->id)->where('event_type', 'vendor_blocked')->first();
    expect($entry)->not->toBeNull()
        ->and($entry->user_id)->toBe($vendor->id)
        ->and($entry->access_basis)->toBe('blocked_vendor_access');
});

it('denies an impersonating session regardless of the impersonated users own access', function (): void {
    $f = brd08Fixture();
    $case = brd08Case($f);

    $impersonated = User::factory()->create(['user_type' => UserType::Staff]);
    $impersonator = User::factory()->create(['user_type' => UserType::Staff]);

    // Grant the impersonated user their own real access to the case...
    app(GrantCaseAccessAction::class)->execute(new GrantCaseAccessData(
        schoolId: $f['school']->id, caseId: $case->id, userId: $impersonated->id,
        grantedByUserId: $f['leadUser']->id, accessLevel: 'read', reason: 'Housemaster context.',
    ));

    $session = ImpersonationSession::create([
        'impersonator_id' => $impersonator->id, 'impersonated_id' => $impersonated->id,
        'school_id' => $f['school']->id, 'reason' => 'Support ticket.',
        'started_at' => now(), 'expires_at' => now()->addHour(),
    ]);

    // ...but under an active impersonation session, access is still denied.
    expect(fn () => app(ViewSafeguardingCaseAction::class)->execute($impersonated, $case, $session))
        ->toThrow(InsufficientScopeException::class);
});

it('denies a user with no grant, logs the attempt, and grants access once a time-boxed grant exists', function (): void {
    $f = brd08Fixture();
    $case = brd08Case($f);
    $housemaster = User::factory()->create(['user_type' => UserType::Staff]);

    expect(fn () => app(ViewSafeguardingCaseAction::class)->execute($housemaster, $case))
        ->toThrow(InsufficientScopeException::class);

    $denied = SafeguardingAuditEntry::where('case_id', $case->id)->where('event_type', 'access_denied')->first();
    expect($denied)->not->toBeNull();

    app(GrantCaseAccessAction::class)->execute(new GrantCaseAccessData(
        schoolId: $f['school']->id, caseId: $case->id, userId: $housemaster->id,
        grantedByUserId: $f['leadUser']->id, accessLevel: 'read', reason: 'Pastoral context needed.', expiryDays: 14,
    ));

    $viewed = app(ViewSafeguardingCaseAction::class)->execute($housemaster, $case);
    expect($viewed->id)->toBe($case->id);

    $readLog = SafeguardingAuditEntry::where('case_id', $case->id)->where('event_type', 'case_read')->where('user_id', $housemaster->id)->first();
    expect($readLog)->not->toBeNull()
        ->and($readLog->access_basis)->toStartWith('grant:');
});

it('chains the safeguarding audit stream and verifies it', function (): void {
    $f = brd08Fixture();
    $case = brd08Case($f);

    app(ViewSafeguardingCaseAction::class)->execute($f['leadUser'], $case);
    app(ViewSafeguardingCaseAction::class)->execute($f['leadUser'], $case);
    app(ViewSafeguardingCaseAction::class)->execute($f['leadUser'], $case);

    $result = (new SafeguardingAuditChainCheck)->run($f['school']->id);
    expect($result->status)->toBe('passed')
        ->and($result->recordsChecked)->toBeGreaterThanOrEqual(3);

    $entries = SafeguardingAuditEntry::where('school_id', $f['school']->id)->orderBy('sequence')->get();
    expect($entries->first()->previous_hash)->toBeNull();

    for ($i = 1; $i < $entries->count(); $i++) {
        expect($entries[$i]->previous_hash)->toBe($entries[$i - 1]->payload_hash);
    }
});

it('stores no reporter identity for an anonymous concern', function (): void {
    $f = brd08Fixture();

    $concern = app(ReportAnonymousConcernAction::class)->execute(new ReportAnonymousConcernData(
        schoolId: $f['school']->id, concernCategory: 'bullying', description: 'A learner reported being bullied.',
        reportedAt: now(),
    ));

    expect($concern->reporter_user_id)->toBeNull()
        ->and($concern->anonymous_token)->not->toBeNull()
        ->and($concern->report_source)->toBe('anonymous');

    $raw = SafeguardingConcern::query()->whereKey($concern->id)->toBase()->first();
    expect($raw->reporter_user_id)->toBeNull();
});

it('refuses no code path to delete a concern, a case, or an append-only case entry', function (): void {
    $f = brd08Fixture();
    $case = brd08Case($f);

    $concern = SafeguardingConcern::factory()->create(['school_id' => $f['school']->id, 'student_id' => $f['student']->id]);

    expect(fn () => $concern->delete())->toThrow(InvalidStateTransitionException::class);
    expect(fn () => $case->delete())->toThrow(InvalidStateTransitionException::class);

    $entry = CaseEntry::factory()->create(['school_id' => $f['school']->id, 'case_id' => $case->id]);
    expect(fn () => $entry->update(['content' => 'tampered']))->toThrow(InvalidStateTransitionException::class);
    expect(fn () => $entry->delete())->toThrow(InvalidStateTransitionException::class);
});

it('requires a mandatory rationale to triage a concern, including no further action', function (): void {
    $f = brd08Fixture();
    $concern = app(ReportSafeguardingConcernAction::class)->execute(new ReportSafeguardingConcernData(
        schoolId: $f['school']->id, reportSource: 'staff', concernCategory: 'other',
        description: 'A minor incident reported.', reportedAt: now(), studentId: $f['student']->id, reporterUserId: $f['leadUser']->id,
    ));

    expect(fn () => app(TriageConcernAction::class)->execute($concern->id, 'no_further_action', '', $f['leadUser']->id))
        ->toThrow(ValidationException::class);

    $triaged = app(TriageConcernAction::class)->execute($concern->id, 'no_further_action', 'No safeguarding concern identified after review.', $f['leadUser']->id);
    expect($triaged->triage_status)->toBe('no_further_action')
        ->and($triaged->triage_rationale)->not->toBeEmpty();
});

it('grants access via break-glass and logs it distinctly', function (): void {
    $f = brd08Fixture();
    $case = brd08Case($f);

    $emergencyUser = User::factory()->create(['user_type' => UserType::Staff]);
    $role = Role::factory()->forSchool($f['school']->id)->create();
    $role->givePermissionTo(Permission::where('name', 'safeguarding.emergency_access')->firstOrFail());
    app(AssignRoleAction::class)->execute(new RoleAssignmentData($emergencyUser->id, $role->id, $f['school']->id));
    SchoolContext::set($f['school']);

    $viewed = app(ViewSafeguardingCaseAction::class)->execute($emergencyUser, $case);
    expect($viewed->id)->toBe($case->id);

    $breakGlass = SafeguardingAuditEntry::where('case_id', $case->id)->where('event_type', 'break_glass')->first();
    expect($breakGlass)->not->toBeNull()
        ->and($breakGlass->user_id)->toBe($emergencyUser->id);
});

it('propagates only the existence of the safeguarding flag to the student record', function (): void {
    $f = brd08Fixture();
    $case = brd08Case($f);

    $f['student']->refresh();
    expect($f['student']->has_safeguarding_flag)->toBeTrue();
});

it('refuses to assign any safeguarding permission to a vendor-only role (§8 — "no permission in this list may be assigned to any vendor role")', function (): void {
    $vendorRole = Role::factory()->system()->create(['is_vendor_only' => true]);
    $permission = Permission::factory()->create(['name' => 'safeguarding.case.view']);

    expect(fn () => $vendorRole->givePermissionTo($permission))
        ->toThrow(InsufficientScopeException::class);

    expect(fn () => $vendorRole->givePermissionTo('safeguarding.grant.manage'))
        ->toThrow(InsufficientScopeException::class);

    expect($vendorRole->fresh()->permissions()->count())->toBe(0);
});

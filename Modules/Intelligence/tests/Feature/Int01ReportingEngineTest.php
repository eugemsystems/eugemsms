<?php

use App\Models\User;
use Modules\Core\Domain\Actions\Settings\SetSettingValueAction;
use Modules\Core\Domain\DataObjects\Settings\SetSettingValueData;
use Modules\Core\Domain\Exceptions\InsufficientScopeException;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Domain\Support\Settings\SettingScope;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;
use Modules\Core\Models\Tenant;
use Modules\Intelligence\Domain\Actions\CreateCustomReportAction;
use Modules\Intelligence\Domain\Actions\ExecuteCustomReportAction;
use Modules\Intelligence\Domain\Actions\GetAvailableFieldsForUserAction;
use Modules\Intelligence\Domain\Actions\RunSavedReportAction;
use Modules\Intelligence\Domain\Actions\ShareReportAction;
use Modules\Intelligence\Domain\DataObjects\CreateCustomReportData;
use Modules\Intelligence\Domain\DataObjects\ExecuteReportSpec;
use Modules\Intelligence\Models\ReportExecution;
use Modules\Payroll\Models\PayGradeNotch;
use Modules\People\Models\Student;

/**
 * @return array{school: School, user: User}
 */
function int01Fixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $user = User::factory()->create();

    // spatie/laravel-permission throws PermissionDoesNotExist the moment
    // ANY code checks a permission string that has never been created as
    // a real row — even when checking whether a user LACKS it. Every
    // permission this module's registered fields reference must exist
    // before any hasPermissionTo() call touches it, in every test, not
    // just the ones actually granted to a user.
    foreach (['people.student.view', 'staff.view_compensation', 'report.sensitive_field.access', 'core.school.view.group', 'payroll.pay_grade.view'] as $name) {
        int01EnsurePermissionExists($name);
    }

    return compact('school', 'user');
}

function int01EnsurePermissionExists(string $name): Permission
{
    return Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'], [
        'module_code' => 'TEST', 'resource' => 'test', 'action' => 'view',
    ]);
}

function int01GivePermission(User $user, string $name): void
{
    $user->givePermissionTo(int01EnsurePermissionExists($name));
}

it('excludes a compensation field from the picker without staff.view_compensation, and refuses a direct request for it (AC-INT-01-001)', function (): void {
    $f = int01Fixture();
    int01GivePermission($f['user'], 'people.student.view');

    $fields = app(GetAvailableFieldsForUserAction::class)->execute('pay_grade_notch', $f['user']);
    expect(collect($fields)->pluck('fieldKey'))->not->toContain('basic_salary_minor');

    $spec = new ExecuteReportSpec(
        schoolId: $f['school']->id, primaryEntityKey: 'pay_grade_notch',
        selectedFields: [['entity' => 'pay_grade_notch', 'field' => 'basic_salary_minor']],
    );

    expect(fn () => app(ExecuteCustomReportAction::class)->execute($spec, $f['user'], strict: true))
        ->toThrow(InsufficientScopeException::class);
});

it('re-evaluates a shared report against the viewer\'s own permissions, never the creator\'s (AC-INT-01-002)', function (): void {
    $f = int01Fixture();
    int01GivePermission($f['user'], 'staff.view_compensation');
    int01GivePermission($f['user'], 'people.student.view');

    $classTeacher = User::factory()->create();
    int01GivePermission($classTeacher, 'people.student.view');

    Student::factory()->for($f['school'])->create(['first_name' => 'Tariro']);

    $report = app(CreateCustomReportAction::class)->execute(new CreateCustomReportData(
        schoolId: $f['school']->id, name: 'Head Report', primaryEntityKey: 'student',
        selectedFields: [['entity' => 'student', 'field' => 'first_name']],
        createdByUserId: $f['user']->id,
    ));

    $asHead = app(RunSavedReportAction::class)->execute($report->id, $f['user']);
    $asClassTeacher = app(RunSavedReportAction::class)->execute($report->id, $classTeacher);

    expect($asHead->rows)->toHaveCount(1)
        ->and($asHead->rows[0])->toHaveKey('first_name')
        ->and($asClassTeacher->rows)->toHaveCount(1)
        ->and($asClassTeacher->rows[0])->toHaveKey('first_name');
});

it('drops a field from a saved report\'s next run once the permission behind it is revoked (AC-INT-01-003)', function (): void {
    $f = int01Fixture();
    $permission = int01EnsurePermissionExists('staff.view_compensation');
    $f['user']->givePermissionTo($permission);
    int01GivePermission($f['user'], 'report.sensitive_field.access');
    int01GivePermission($f['user'], 'payroll.pay_grade.view');

    PayGradeNotch::factory()->for($f['school'])->create(['notch' => 1, 'basic_salary_minor' => 50000]);

    $report = app(CreateCustomReportAction::class)->execute(new CreateCustomReportData(
        schoolId: $f['school']->id, name: 'Salary Report', primaryEntityKey: 'pay_grade_notch',
        selectedFields: [['entity' => 'pay_grade_notch', 'field' => 'notch'], ['entity' => 'pay_grade_notch', 'field' => 'basic_salary_minor']],
        createdByUserId: $f['user']->id,
    ));

    $before = app(RunSavedReportAction::class)->execute($report->id, $f['user']);
    expect($before->rows[0])->toHaveKey('basic_salary_minor');

    $f['user']->revokePermissionTo($permission);

    $after = app(RunSavedReportAction::class)->execute($report->id, $f['user']->fresh());
    expect($after->rows[0])->toHaveKey('notch')
        ->and($after->rows[0])->not->toHaveKey('basic_salary_minor');
});

it('refuses cross-school consolidation without core.school.view.group, regardless of tenant settings (AC-INT-01-004)', function (): void {
    $f = int01Fixture();
    int01GivePermission($f['user'], 'people.student.view');
    Tenant::where('id', $f['school']->tenant_id)->update(['is_group_reporting_enabled' => true]);

    $spec = new ExecuteReportSpec(
        schoolId: $f['school']->id, primaryEntityKey: 'student',
        selectedFields: [['entity' => 'student', 'field' => 'first_name']],
        consolidateSchoolIds: [$f['school']->id],
    );

    expect(fn () => app(ExecuteCustomReportAction::class)->execute($spec, $f['user']))
        ->toThrow(InsufficientScopeException::class);
});

it('redirects an ad hoc query away from live execution once it would scan beyond the configured row limit (AC-INT-01-005)', function (): void {
    $f = int01Fixture();
    int01GivePermission($f['user'], 'people.student.view');

    Student::factory()->for($f['school'])->count(3)->create();

    app(SetSettingValueAction::class)->execute(new SetSettingValueData('reporting.ad_hoc_row_limit', SettingScope::School, $f['school']->id, '2'));

    $spec = new ExecuteReportSpec(
        schoolId: $f['school']->id, primaryEntityKey: 'student',
        selectedFields: [['entity' => 'student', 'field' => 'first_name']],
    );

    $result = app(ExecuteCustomReportAction::class)->execute($spec, $f['user']);

    expect($result->wasRedirected)->toBeTrue()
        ->and($result->rows)->toBe([])
        ->and($result->redirectReason)->not->toBeNull();
});

it('logs every execution — ad hoc or saved — to report_executions (BR-INT-01-009)', function (): void {
    $f = int01Fixture();
    int01GivePermission($f['user'], 'people.student.view');
    Student::factory()->for($f['school'])->create();

    app(ExecuteCustomReportAction::class)->execute(new ExecuteReportSpec(
        schoolId: $f['school']->id, primaryEntityKey: 'student',
        selectedFields: [['entity' => 'student', 'field' => 'first_name']],
    ), $f['user']);

    $execution = ReportExecution::where('school_id', $f['school']->id)->firstOrFail();
    expect($execution->report_id)->toBeNull()
        ->and($execution->executed_by)->toBe($f['user']->id)
        ->and($execution->row_count)->toBe(1);
});

it('shares a report without copying the sharer\'s own access', function (): void {
    $f = int01Fixture();
    int01GivePermission($f['user'], 'people.student.view');

    $report = app(CreateCustomReportAction::class)->execute(new CreateCustomReportData(
        schoolId: $f['school']->id, name: 'Shared Report', primaryEntityKey: 'student',
        selectedFields: [['entity' => 'student', 'field' => 'first_name']],
        createdByUserId: $f['user']->id,
    ));
    $viewer = User::factory()->create();

    $share = app(ShareReportAction::class)->execute($report->id, 'user', $viewer->id, $f['user']->id);

    expect($share->isSharedWithUser($viewer->id))->toBeTrue()
        ->and($share->can_edit)->toBeFalse();
});

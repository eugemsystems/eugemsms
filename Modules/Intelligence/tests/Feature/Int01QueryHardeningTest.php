<?php

use App\Models\User;
use Modules\Core\Domain\Exceptions\InsufficientScopeException;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\School;
use Modules\Intelligence\Domain\Actions\CreateCustomReportAction;
use Modules\Intelligence\Domain\Actions\ExecuteCustomReportAction;
use Modules\Intelligence\Domain\Actions\GetAvailableFieldsForUserAction;
use Modules\Intelligence\Domain\Actions\RunSavedReportAction;
use Modules\Intelligence\Domain\Actions\ShareReportAction;
use Modules\Intelligence\Domain\DataObjects\CreateCustomReportData;
use Modules\Intelligence\Domain\DataObjects\ExecuteReportSpec;
use Modules\Intelligence\Models\ReportExecution;
use Spatie\Permission\Models\Permission;

/**
 * Book J INT-01 admin-UI pass — regression tests for three gaps found
 * while exposing the builder: a filter or group-by on a field the
 * runner cannot read (an inference oracle), an unvalidated column alias
 * concatenated into SQL, and a saved report runnable by anyone who knew
 * its id. Own, distinctly-named helpers.
 *
 * @return array{school: School, user: User}
 */
function int01HardFixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);

    foreach (['people.student.view', 'staff.view_compensation', 'report.sensitive_field.access', 'payroll.pay_grade.view'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'], ['module_code' => 'TEST', 'resource' => 'test', 'action' => 'view']);
    }

    $user = User::factory()->create();
    $user->givePermissionTo('people.student.view');

    return ['school' => $school, 'user' => $user];
}

it('refuses a filter on a field the runner cannot read, even when it is not selected (AC-INT-01-001)', function (): void {
    $f = int01HardFixture();

    $spec = new ExecuteReportSpec(
        schoolId: $f['school']->id, primaryEntityKey: 'pay_grade_notch',
        selectedFields: [],
        filters: [['field' => 'basic_salary_minor', 'operator' => 'gt', 'value' => 5000]],
    );

    expect(fn () => app(ExecuteCustomReportAction::class)->execute($spec, $f['user']))->toThrow(InsufficientScopeException::class);
    expect(fn () => app(ExecuteCustomReportAction::class)->execute($spec, $f['user'], strict: false))->toThrow(InsufficientScopeException::class);
});

it('refuses a group-by on a field the runner cannot read', function (): void {
    $f = int01HardFixture();

    $spec = new ExecuteReportSpec(
        schoolId: $f['school']->id, primaryEntityKey: 'pay_grade_notch',
        selectedFields: [['entity' => 'pay_grade_notch', 'field' => 'notch']],
        groupBy: ['basic_salary_minor'],
    );

    expect(fn () => app(ExecuteCustomReportAction::class)->execute($spec, $f['user'], strict: false))->toThrow(InsufficientScopeException::class);
});

it('refuses a filter or group-by on a column that is not a registered field', function (): void {
    $f = int01HardFixture();

    $filter = new ExecuteReportSpec(
        schoolId: $f['school']->id, primaryEntityKey: 'student',
        selectedFields: [['entity' => 'student', 'field' => 'first_name']],
        filters: [['field' => 'date_of_birth', 'operator' => 'eq', 'value' => '2010-01-01']],
    );
    $group = new ExecuteReportSpec(
        schoolId: $f['school']->id, primaryEntityKey: 'student',
        selectedFields: [['entity' => 'student', 'field' => 'first_name']],
        groupBy: ['password'],
    );

    expect(fn () => app(ExecuteCustomReportAction::class)->execute($filter, $f['user']))->toThrow(InvalidArgumentException::class);
    expect(fn () => app(ExecuteCustomReportAction::class)->execute($group, $f['user']))->toThrow(InvalidArgumentException::class);
});

it('still allows a filter on a field the runner may read', function (): void {
    $f = int01HardFixture();

    $spec = new ExecuteReportSpec(
        schoolId: $f['school']->id, primaryEntityKey: 'student',
        selectedFields: [['entity' => 'student', 'field' => 'first_name']],
        filters: [['field' => 'gender', 'operator' => 'eq', 'value' => 'female']],
    );

    expect(app(ExecuteCustomReportAction::class)->execute($spec, $f['user'])->rowCount)->toBe(0);
});

it('rejects a column alias that is not a plain identifier', function (string $alias): void {
    $f = int01HardFixture();

    $spec = new ExecuteReportSpec(
        schoolId: $f['school']->id, primaryEntityKey: 'student',
        selectedFields: [['entity' => 'student', 'field' => 'first_name', 'alias' => $alias]],
    );

    expect(fn () => app(ExecuteCustomReportAction::class)->execute($spec, $f['user']))->toThrow(InvalidArgumentException::class);
})->with(['injection' => ['x, (select 1) as y'], 'space' => ['two words'], 'leading digit' => ['1abc'], 'quote' => ["a'b"]]);

it('lets only the author or a recipient run a saved report (BR-INT-01-005)', function (): void {
    $f = int01HardFixture();
    $recipient = User::factory()->create();
    $recipient->givePermissionTo('people.student.view');
    $stranger = User::factory()->create();
    $stranger->givePermissionTo('people.student.view');

    $report = app(CreateCustomReportAction::class)->execute(new CreateCustomReportData(
        schoolId: $f['school']->id, name: 'Roll', primaryEntityKey: 'student',
        selectedFields: [['entity' => 'student', 'field' => 'first_name']], createdByUserId: $f['user']->id,
    ));
    app(ShareReportAction::class)->execute($report->id, 'user', $recipient->id, $f['user']->id);

    $run = fn (User $who) => app(RunSavedReportAction::class)->execute($report->id, $who);

    expect($run($f['user'])->rowCount)->toBe(0)
        ->and($run($recipient)->rowCount)->toBe(0)
        ->and(fn () => $run($stranger))->toThrow(InsufficientScopeException::class);

    // The refused attempt wrote no execution row.
    expect(ReportExecution::where('executed_by', $stranger->id)->count())->toBe(0);
});

it('treats a permission that was never created as not permitted instead of failing (BR-INT-01-002)', function (): void {
    $f = int01HardFixture();
    Permission::where('name', 'staff.view_compensation')->delete();
    $f['user']->givePermissionTo('payroll.pay_grade.view');

    $fields = app(GetAvailableFieldsForUserAction::class)->execute('pay_grade_notch', $f['user']);

    expect(collect($fields)->pluck('fieldKey')->all())->toBe(['notch']);
});

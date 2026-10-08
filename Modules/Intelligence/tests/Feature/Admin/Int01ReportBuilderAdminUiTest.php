<?php

use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\Actions\Settings\SetSettingValueAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\DataObjects\Settings\SetSettingValueData;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Domain\Support\Settings\SettingScope;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;
use Modules\Intelligence\Domain\Actions\CreateCustomReportAction;
use Modules\Intelligence\Domain\Actions\ScheduleCustomReportAction;
use Modules\Intelligence\Domain\Actions\ShareReportAction;
use Modules\Intelligence\Domain\DataObjects\CreateCustomReportData;
use Modules\Intelligence\Livewire\Insights\Reports\Builder;
use Modules\Intelligence\Livewire\Insights\Reports\ExecutionLog;
use Modules\Intelligence\Livewire\Insights\Reports\Index;
use Modules\Intelligence\Livewire\Insights\Reports\Schedule;
use Modules\Intelligence\Livewire\Insights\Reports\Shared;
use Modules\Intelligence\Models\CustomReport;
use Modules\Intelligence\Models\CustomReportSchedule;
use Modules\Intelligence\Models\ReportExecution;
use Modules\Intelligence\Models\ReportShare;
use Modules\Payroll\Models\PayGradeNotch;
use Modules\People\Models\Student;

/**
 * Book J INT-01 admin-UI pass. Own, distinctly-named helpers.
 *
 * @return array<string, mixed>
 */
function int01AdminFixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);

    return ['school' => $school];
}

/**
 * Grants through the app's own mechanism, which sets both the spatie
 * permission the report engine checks and the scope row the screens check.
 *
 * @param  array<string, mixed>  $f
 */
function int01AdminUser(array $f, string ...$permissionNames): User
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
 */
function int01AdminReport(array $f, User $author, array $fields = ['first_name'], string $entity = 'student', string $name = 'Roll'): CustomReport
{
    return app(CreateCustomReportAction::class)->execute(new CreateCustomReportData(
        schoolId: $f['school']->id, name: $name, primaryEntityKey: $entity,
        selectedFields: array_map(fn (string $field): array => ['entity' => $entity, 'field' => $field], $fields),
        createdByUserId: $author->id,
    ));
}

it('refuses every permissioned INT-01 screen to a user without its permission', function (string $component): void {
    $f = int01AdminFixture();
    $outsider = User::factory()->create();
    $outsider->schools()->attach($f['school'], ['status' => 'active']);

    Livewire::actingAs($outsider)->test($component, ['school' => $f['school']])->assertForbidden();
})->with([
    'builder' => Builder::class,
    'my reports' => Index::class,
    'schedule' => Schedule::class,
    'execution log' => ExecutionLog::class,
]);

it('lets any school member open Shared but not a stranger to the school', function (): void {
    $f = int01AdminFixture();
    $member = int01AdminUser($f);
    $stranger = User::factory()->create();

    Livewire::actingAs($member)->test(Shared::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($stranger)->test(Shared::class, ['school' => $f['school']])->assertForbidden();
});

it('renders every INT-01 screen for a fully-permissioned user', function (): void {
    $f = int01AdminFixture();
    $user = int01AdminUser($f, 'report.build', 'report.schedule', 'report.view_audit');

    foreach ([Builder::class, Index::class, Shared::class, Schedule::class, ExecutionLog::class] as $component) {
        Livewire::actingAs($user)->test($component, ['school' => $f['school']])->assertOk();
    }
});

it('does not offer a compensation field in the picker to a user without its permission (AC-INT-01-001)', function (): void {
    $f = int01AdminFixture();
    $teacher = int01AdminUser($f, 'report.build', 'payroll.pay_grade.view');
    $head = int01AdminUser($f, 'report.build', 'payroll.pay_grade.view', 'staff.view_compensation', 'report.sensitive_field.access');

    $teacherFields = Livewire::actingAs($teacher)->test(Builder::class, ['school' => $f['school']])
        ->set('entity', 'pay_grade_notch')->viewData('fields');
    $headFields = Livewire::actingAs($head)->test(Builder::class, ['school' => $f['school']])
        ->set('entity', 'pay_grade_notch')->viewData('fields');

    expect(collect($teacherFields)->pluck('fieldKey')->all())->toBe(['notch'])
        ->and(collect($headFields)->pluck('fieldKey')->all())->toContain('basic_salary_minor');
});

it('refuses a crafted request naming a field the user cannot read, in the selection or a filter (AC-INT-01-001)', function (): void {
    $f = int01AdminFixture();
    $teacher = int01AdminUser($f, 'report.build', 'payroll.pay_grade.view');
    PayGradeNotch::factory()->for($f['school'])->create(['notch' => 1, 'basic_salary_minor' => 50000]);

    $component = Livewire::actingAs($teacher)->test(Builder::class, ['school' => $f['school']])
        ->set('entity', 'pay_grade_notch')
        ->set('selected', ['notch', 'basic_salary_minor'])
        ->call('run');
    $component->assertHasErrors(['entity']);
    expect($component->get('result'))->toBeNull();

    $viaFilter = Livewire::actingAs($teacher)->test(Builder::class, ['school' => $f['school']])
        ->set('entity', 'pay_grade_notch')
        ->set('selected', ['notch'])
        ->set('filters', [['field' => 'basic_salary_minor', 'operator' => 'gt', 'value' => '1000', 'group' => 1]])
        ->call('run');
    $viaFilter->assertHasErrors(['entity']);
    expect($viaFilter->get('result'))->toBeNull();
});

it('refuses a filter on a column that is not a registered field', function (): void {
    $f = int01AdminFixture();
    $user = int01AdminUser($f, 'report.build', 'people.student.view');

    Livewire::actingAs($user)->test(Builder::class, ['school' => $f['school']])
        ->set('entity', 'student')->set('selected', ['first_name'])
        ->set('filters', [['field' => 'password', 'operator' => 'eq', 'value' => 'x', 'group' => 1]])
        ->call('run')
        ->assertHasErrors(['entity']);
});

it('runs a filtered report, then saves it (BR-INT-01-001)', function (): void {
    $f = int01AdminFixture();
    $user = int01AdminUser($f, 'report.build', 'people.student.view');
    Student::factory()->for($f['school'])->create(['first_name' => 'Tariro', 'gender' => 'female']);
    Student::factory()->for($f['school'])->create(['first_name' => 'Tendai', 'gender' => 'male']);
    SchoolContext::set($f['school']);

    $component = Livewire::actingAs($user)->test(Builder::class, ['school' => $f['school']])
        ->set('entity', 'student')->set('selected', ['first_name', 'gender'])
        ->set('filters', [['field' => 'gender', 'operator' => 'eq', 'value' => 'female', 'group' => 1]])
        ->call('run')
        ->assertHasNoErrors();

    expect($component->get('result')['rows'])->toBe([['first_name' => 'Tariro', 'gender' => 'female']]);

    $component->set('name', 'Girls')->call('save')->assertHasNoErrors();

    $saved = CustomReport::where('school_id', $f['school']->id)->firstOrFail();
    expect($saved->name)->toBe('Girls')->and($saved->created_by)->toBe($user->id)
        ->and($saved->filters[0]['field'])->toBe('gender');
    expect(ReportExecution::where('executed_by', $user->id)->count())->toBeGreaterThanOrEqual(2);
});

it('groups and totals, offering totals only for aggregatable selected fields', function (): void {
    $f = int01AdminFixture();
    $user = int01AdminUser($f, 'report.build', 'people.student.view');
    Student::factory()->for($f['school'])->count(2)->create(['gender' => 'female']);
    Student::factory()->for($f['school'])->create(['gender' => 'male']);
    SchoolContext::set($f['school']);

    $component = Livewire::actingAs($user)->test(Builder::class, ['school' => $f['school']])
        ->set('entity', 'student')->set('selected', ['gender', 'first_name']);

    expect(collect($component->viewData('aggregatable'))->pluck('fieldKey')->all())->toBe(['gender']);

    $component->set('groupBy', ['gender'])->set('aggregations', [['field' => 'gender', 'function' => 'count']])->set('selected', ['gender'])
        ->call('run')->assertHasNoErrors();

    $rows = collect($component->get('result')['rows'])->keyBy('gender');
    expect($rows['female']['gender_count'])->toBe(2)->and($rows['male']['gender_count'])->toBe(1);
});

it('redirects a query over the live row budget instead of running it (AC-INT-01-005)', function (): void {
    $f = int01AdminFixture();
    $user = int01AdminUser($f, 'report.build', 'people.student.view');
    Student::factory()->for($f['school'])->count(3)->create();
    SchoolContext::set($f['school']);
    app(SetSettingValueAction::class)->execute(new SetSettingValueData(
        key: 'reporting.ad_hoc_row_limit', scopeType: SettingScope::School, scopeId: $f['school']->id, value: 2, setByUserId: $user->id,
    ));

    $component = Livewire::actingAs($user)->test(Builder::class, ['school' => $f['school']])
        ->set('entity', 'student')->set('selected', ['first_name'])->call('run');

    expect($component->get('result')['wasRedirected'])->toBeTrue()
        ->and($component->get('result')['rows'])->toBe([])
        ->and($component->get('result')['redirectReason'])->toContain('above the configured limit');
});

it('refuses cross-school consolidation without core.school.view.group, whatever the page sends (AC-INT-01-004)', function (): void {
    $f = int01AdminFixture();
    $user = int01AdminUser($f, 'report.build', 'people.student.view');

    $component = Livewire::actingAs($user)->test(Builder::class, ['school' => $f['school']])
        ->set('entity', 'student')->set('selected', ['first_name'])
        ->set('consolidate', true)->call('run');

    $component->assertHasErrors(['entity']);
    expect($component->get('result'))->toBeNull()->and($component->viewData('canConsolidate'))->toBeFalse();
});

it('re-evaluates a shared report against the viewer, so a field they cannot read does not come back (AC-INT-01-002)', function (): void {
    $f = int01AdminFixture();
    $head = int01AdminUser($f, 'report.build', 'payroll.pay_grade.view', 'staff.view_compensation', 'report.sensitive_field.access');
    $teacher = int01AdminUser($f, 'report.build', 'payroll.pay_grade.view');
    PayGradeNotch::factory()->for($f['school'])->create(['notch' => 1, 'basic_salary_minor' => 50000]);
    $report = int01AdminReport($f, $head, ['notch', 'basic_salary_minor'], 'pay_grade_notch', 'Pay');
    SchoolContext::set($f['school']);

    Livewire::actingAs($head)->test(Index::class, ['school' => $f['school']])
        ->set('shareReportId', $report->id)->set('shareWithUserId', $teacher->id)->call('share')->assertHasNoErrors();

    $asHead = Livewire::actingAs($head)->test(Index::class, ['school' => $f['school']])->call('run', $report->id);
    expect(array_keys($asHead->get('result')['rows'][0]))->toContain('basic_salary_minor');

    $asTeacher = Livewire::actingAs($teacher)->test(Shared::class, ['school' => $f['school']])
        ->assertSee('Pay')->call('run', $report->id);
    expect(array_keys($asTeacher->get('result')['rows'][0]))->toBe(['notch']);
});

it('shows nothing that has not been shared, and refuses running it (BR-INT-01-005)', function (): void {
    $f = int01AdminFixture();
    $author = int01AdminUser($f, 'report.build', 'people.student.view');
    $other = int01AdminUser($f, 'people.student.view');
    $report = int01AdminReport($f, $author);
    SchoolContext::set($f['school']);

    $component = Livewire::actingAs($other)->test(Shared::class, ['school' => $f['school']])->assertDontSee('Roll');

    expect(fn () => $component->call('run', $report->id))->toThrow(ModelNotFoundException::class);
});

it('lets only the author share a report, and only with a colleague from the same school', function (): void {
    $f = int01AdminFixture();
    $author = int01AdminUser($f, 'report.build');
    $other = int01AdminUser($f, 'report.build');
    $outsider = User::factory()->create();
    $report = int01AdminReport($f, $author);
    SchoolContext::set($f['school']);

    $asOther = Livewire::actingAs($other)->test(Index::class, ['school' => $f['school']]);
    expect(fn () => $asOther->set('shareReportId', $report->id)->set('shareWithUserId', $author->id)->call('share'))->toThrow(ModelNotFoundException::class);

    $asAuthor = Livewire::actingAs($author)->test(Index::class, ['school' => $f['school']]);
    expect(fn () => $asAuthor->set('shareReportId', $report->id)->set('shareWithUserId', $outsider->id)->call('share'))->toThrow(ModelNotFoundException::class);

    expect(ReportShare::where('report_id', $report->id)->count())->toBe(0);
});

it('schedules one of my own reports for school colleagues only (BR-INT-01-010)', function (): void {
    $f = int01AdminFixture();
    $author = int01AdminUser($f, 'report.schedule');
    $colleague = int01AdminUser($f);
    $outsider = User::factory()->create();
    $report = int01AdminReport($f, $author);
    $foreign = int01AdminReport($f, $colleague, name: 'Theirs');
    SchoolContext::set($f['school']);

    $component = Livewire::actingAs($author)->test(Schedule::class, ['school' => $f['school']])
        ->set('reportId', $report->id)->set('frequency', 'monthly')->set('format', 'pdf')
        ->set('recipientIds', [$colleague->id, $outsider->id])
        ->call('schedule')->assertHasNoErrors();

    $schedule = CustomReportSchedule::where('report_id', $report->id)->firstOrFail();
    expect($schedule->frequency)->toBe('monthly')
        ->and(collect($schedule->recipients)->pluck('recipientId')->all())->toBe([$colleague->id]);

    $component->set('reportId', $report->id)->set('recipientIds', [$outsider->id])->call('schedule')->assertHasErrors(['recipientIds']);
    expect(fn () => $component->set('reportId', $foreign->id)->set('recipientIds', [$colleague->id])->call('schedule'))->toThrow(ModelNotFoundException::class);
});

it('lets only the author edit or delete their own report', function (): void {
    $f = int01AdminFixture();
    $author = int01AdminUser($f, 'report.build');
    $other = int01AdminUser($f, 'report.build');
    $report = int01AdminReport($f, $author);
    SchoolContext::set($f['school']);

    $asOther = Livewire::actingAs($other)->test(Index::class, ['school' => $f['school']]);
    expect(fn () => $asOther->call('editStart', $report->id))->toThrow(ModelNotFoundException::class);
    expect(fn () => $asOther->call('delete', $report->id))->toThrow(ModelNotFoundException::class);

    $asAuthor = Livewire::actingAs($author)->test(Index::class, ['school' => $f['school']]);
    $asAuthor->call('editStart', $report->id)
        ->assertSet('editName', $report->name)
        ->set('editName', 'Renamed Roll')
        ->set('editChartType', 'bar')
        ->call('saveEdit')
        ->assertHasNoErrors();

    expect($report->fresh()->name)->toBe('Renamed Roll')
        ->and($report->fresh()->chart_type)->toBe('bar');

    $asAuthor->call('delete', $report->id);
    expect(CustomReport::find($report->id))->toBeNull();
});

it('deleting a report cascades its schedules and shares', function (): void {
    $f = int01AdminFixture();
    $author = int01AdminUser($f, 'report.build', 'report.schedule');
    $colleague = int01AdminUser($f);
    $report = int01AdminReport($f, $author);
    SchoolContext::set($f['school']);

    app(ShareReportAction::class)->execute($report->id, 'user', $colleague->id, $author->id);
    app(ScheduleCustomReportAction::class)->execute($report->id, 'weekly', [['recipientType' => 'user', 'recipientId' => $colleague->id, 'channel' => 'email']], 'csv');

    Livewire::actingAs($author)->test(Index::class, ['school' => $f['school']])->call('delete', $report->id);

    expect(ReportShare::where('report_id', $report->id)->count())->toBe(0)
        ->and(CustomReportSchedule::where('report_id', $report->id)->count())->toBe(0);
});

it('pauses, resumes, and deletes a report schedule, only for its own author', function (): void {
    $f = int01AdminFixture();
    $author = int01AdminUser($f, 'report.schedule', 'report.build');
    $report = int01AdminReport($f, $author);
    SchoolContext::set($f['school']);

    $schedule = app(ScheduleCustomReportAction::class)->execute(
        $report->id, 'weekly', [['recipientType' => 'user', 'recipientId' => $author->id, 'channel' => 'email']], 'csv',
    );

    $stranger = int01AdminUser($f, 'report.schedule');
    expect(fn () => Livewire::actingAs($stranger)->test(Schedule::class, ['school' => $f['school']])->call('pause', $schedule->id))->toThrow(ModelNotFoundException::class);

    $component = Livewire::actingAs($author)->test(Schedule::class, ['school' => $f['school']]);
    $component->call('pause', $schedule->id);
    expect($schedule->fresh()->is_active)->toBeFalse();

    $component->call('pause', $schedule->id);
    expect($schedule->fresh()->is_active)->toBeTrue();

    $component->call('delete', $schedule->id);
    expect(CustomReportSchedule::find($schedule->id))->toBeNull();
});

it('lists executions without ever showing a report’s results (BR-INT-01-009)', function (): void {
    $f = int01AdminFixture();
    $auditor = int01AdminUser($f, 'report.view_audit');
    $runner = int01AdminUser($f, 'report.build', 'people.student.view');
    Student::factory()->for($f['school'])->create(['first_name' => 'UNIQUE-NAME-XYZ']);
    SchoolContext::set($f['school']);

    Livewire::actingAs($runner)->test(Builder::class, ['school' => $f['school']])
        ->set('entity', 'student')->set('selected', ['first_name'])->call('run');

    Livewire::actingAs($auditor)->test(ExecutionLog::class, ['school' => $f['school']])
        ->assertSee($runner->name)->assertSee('ad hoc')->assertDontSee('UNIQUE-NAME-XYZ');
});

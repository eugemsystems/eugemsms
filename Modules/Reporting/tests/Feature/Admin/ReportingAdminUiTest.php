<?php

use App\Models\User;
use Livewire\Livewire;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\Actions\Documents\CreateNumberingSeriesAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\DataObjects\Documents\CreateNumberingSeriesData;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\Journal;
use Modules\Finance\Models\JournalLine;
use Modules\Reporting\Livewire\Close\Checklist;
use Modules\Reporting\Livewire\Export\Accounting as ExportAccounting;
use Modules\Reporting\Livewire\Financial\BalanceSheet;
use Modules\Reporting\Livewire\Financial\CashFlow;
use Modules\Reporting\Livewire\Financial\IncomeStatement;
use Modules\Reporting\Livewire\Financial\Management;
use Modules\Reporting\Livewire\Financial\TrialBalance;
use Modules\Reporting\Livewire\Schedules\Index as SchedulesIndex;
use Modules\Reporting\Models\PeriodCloseChecklist;

/**
 * Book H3 FIN-12 admin-UI pass. Own, distinctly-named fixture
 * (`reportingAdminFixture`/`reportingAdminUser`) — `fin12Fixture`
 * already exists in the sibling backend test file.
 *
 * @return array<string, mixed>
 */
function reportingAdminFixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->current()->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->current()->create([
        'starts_on' => now()->subMonth()->startOfMonth()->toDateString(),
        'ends_on' => now()->addMonth()->endOfMonth()->toDateString(),
    ]);
    $user = User::factory()->create();

    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
        schoolId: $school->id, documentType: 'journal', pattern: 'ARJ/{SEQ:6}',
    ));

    $cash = Account::factory()->for($school)->create();
    $income = Account::factory()->for($school)->income()->create();

    return compact('school', 'year', 'term', 'user', 'cash', 'income');
}

/**
 * @param  array<string, mixed>  $f
 */
function reportingAdminUser(array $f, string ...$permissionNames): User
{
    $user = User::factory()->create();
    $user->schools()->attach($f['school'], ['status' => 'active']);

    $grants = array_map(function (string $permissionName): PermissionGrantData {
        $lastDot = strrpos($permissionName, '.');
        $moduleCode = strtoupper(substr($permissionName, 0, strpos($permissionName, '.')));
        $action = substr($permissionName, $lastDot + 1);
        $resource = substr($permissionName, strpos($permissionName, '.') + 1, $lastDot - strpos($permissionName, '.') - 1);
        $resource = $resource !== '' ? $resource : $action;

        $permission = Permission::firstOrCreate(
            ['name' => $permissionName],
            ['guard_name' => 'web', 'module_code' => $moduleCode, 'resource' => $resource, 'action' => $action],
        );

        return new PermissionGrantData($permission->id, PermissionScope::School);
    }, $permissionNames);

    app(UpdateUserPermissionsAction::class)->execute(new UserPermissionData(
        userId: $user->id, schoolId: $f['school']->id, grants: $grants,
    ));

    return $user;
}

it('refuses to mount the close checklist screen for a user with no reporting.period.close grant', function (): void {
    $f = reportingAdminFixture();
    $outsider = User::factory()->create();
    $outsider->schools()->attach($f['school'], ['status' => 'active']);

    Livewire::actingAs($outsider)->test(Checklist::class, ['school' => $f['school']])
        ->assertForbidden();
});

it('renders every reporting screen for a fully-permissioned user', function (): void {
    $f = reportingAdminFixture();
    $user = reportingAdminUser(
        $f,
        'reporting.report.trial_balance', 'reporting.report.view', 'reporting.period.close',
        'reporting.report.schedule', 'reporting.report.export',
    );

    Livewire::actingAs($user)->test(TrialBalance::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(IncomeStatement::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(BalanceSheet::class, ['school' => $f['school']])->call('generate')->assertHasNoErrors()->assertSet('isBalanced', true);
    Livewire::actingAs($user)->test(CashFlow::class, ['school' => $f['school']])->call('generate')->assertHasNoErrors();
    Livewire::actingAs($user)->test(Management::class, ['school' => $f['school']])->assertOk()->set('tab', 'collection')->assertOk();
    Livewire::actingAs($user)->test(Checklist::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(SchedulesIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(ExportAccounting::class, ['school' => $f['school']])->assertOk();
});

it('fails the close checklist as blocking on an unbalanced trial balance and refuses to let it be acknowledged away (AC-FIN-12-004)', function (): void {
    $f = reportingAdminFixture();
    $user = reportingAdminUser($f, 'reporting.period.close');

    // A deliberately unbalanced journal, inserted directly (bypassing
    // `PostJournalAction`'s own balance assertion) to prove the CHECK
    // catches it independently — the same construction the sibling
    // backend test uses for the identical acceptance criterion.
    $journal = Journal::factory()->create([
        'school_id' => $f['school']->id, 'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id,
        'effective_at' => now(), 'posted_at' => now(),
    ]);
    JournalLine::factory()->create([
        'school_id' => $f['school']->id, 'journal_id' => $journal->id, 'account_id' => $f['cash']->id,
        'direction' => 'DR', 'amount_minor' => 10000, 'currency' => 'USD', 'term_id' => $f['term']->id,
        'effective_at' => now(),
    ]);
    JournalLine::factory()->create([
        'school_id' => $f['school']->id, 'journal_id' => $journal->id, 'account_id' => $f['income']->id,
        'direction' => 'CR', 'amount_minor' => 9000, 'currency' => 'USD', 'term_id' => $f['term']->id,
        'effective_at' => now(),
    ]);

    $component = Livewire::actingAs($user)->test(Checklist::class, ['school' => $f['school']])
        ->set('termId', $f['term']->id)
        ->set('periodType', 'financial')
        ->call('run');

    $checklistId = $component->get('selectedChecklistId');
    expect(PeriodCloseChecklist::find($checklistId)->overall_status)->toBe('failed');

    Livewire::actingAs($user)->test(Checklist::class, ['school' => $f['school']])
        ->set('selectedChecklistId', $checklistId)
        ->set('acknowledgeCheckKey', 'fin01_trial_balance_balances')
        ->set('acknowledgeReason', 'Approved anyway.')
        ->call('acknowledge')
        ->assertDispatched('toast', fn (string $name, array $params): bool => $params['variant'] === 'danger');
});

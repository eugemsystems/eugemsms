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
use Modules\Payroll\Domain\Actions\AddStaffPayComponentAction;
use Modules\Payroll\Domain\Actions\CreatePayComponentAction;
use Modules\Payroll\Domain\Actions\CreateStaffPayStructureAction;
use Modules\Payroll\Domain\Actions\CreateStatutoryConfigurationAction;
use Modules\Payroll\Domain\DataObjects\AddStaffPayComponentData;
use Modules\Payroll\Domain\DataObjects\CreatePayComponentData;
use Modules\Payroll\Domain\DataObjects\CreateStaffPayStructureData;
use Modules\Payroll\Domain\DataObjects\CreateStatutoryConfigurationData;
use Modules\Payroll\Livewire\Components\Index as ComponentsIndex;
use Modules\Payroll\Livewire\Grades\Index as GradesIndex;
use Modules\Payroll\Livewire\Loans\Index as LoansIndex;
use Modules\Payroll\Livewire\Payslips\Show as PayslipsShow;
use Modules\Payroll\Livewire\Reports\Summary as ReportsSummary;
use Modules\Payroll\Livewire\Returns\Index as ReturnsIndex;
use Modules\Payroll\Livewire\Returns\Itf16 as ReturnsItf16;
use Modules\Payroll\Livewire\Run\BankFile as RunBankFile;
use Modules\Payroll\Livewire\Run\Wizard as RunWizard;
use Modules\Payroll\Livewire\Staff\Structure as StaffStructure;
use Modules\Payroll\Livewire\Statutory\Config as StatutoryConfig;
use Modules\Payroll\Models\Payslip;
use Modules\People\Domain\Actions\CreateStaffAction;
use Modules\People\Domain\DataObjects\CreateStaffData;
use Modules\People\Models\Staff;

/**
 * Book H3 PPL-05 admin-UI pass. Own, distinctly-named fixture
 * (`payrollAdminFixture`/`payrollAdminUser`/`payrollAdminStaff`) —
 * `ppl05Fixture`/`createPayrollStaff` already exist in the sibling
 * backend test file and Pest loads every file together in a full
 * suite run.
 *
 * @return array<string, mixed>
 */
function payrollAdminFixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->current()->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->current()->create(['financial_state' => 'open']);
    $user = User::factory()->create();

    foreach (['staff' => 'ASF', 'payroll_run' => 'ARN', 'payslip' => 'APS', 'journal' => 'AJL'] as $documentType => $prefix) {
        app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
            schoolId: $school->id, documentType: $documentType, pattern: $prefix.'/{SEQ:6}',
        ));
    }

    app(CreateStatutoryConfigurationAction::class)->execute(new CreateStatutoryConfigurationData(
        configType: 'nssa_pension',
        configuration: ['employee_rate' => '0.045', 'employer_rate' => '0.045', 'ceiling_minor' => 70000],
        effectiveFrom: now()->subYear(), createdByUserId: $user->id, currency: 'USD',
    ));

    app(CreateStatutoryConfigurationAction::class)->execute(new CreateStatutoryConfigurationData(
        configType: 'paye_bands',
        configuration: ['bands' => [['from_minor' => 0, 'to_minor' => null, 'rate' => '0.10']]],
        effectiveFrom: now()->subYear(), createdByUserId: $user->id, currency: 'USD',
    ));

    app(CreateStatutoryConfigurationAction::class)->execute(new CreateStatutoryConfigurationData(
        configType: 'aids_levy', configuration: ['rate' => '0.03'], effectiveFrom: now()->subYear(), createdByUserId: $user->id,
    ));

    app(CreateStatutoryConfigurationAction::class)->execute(new CreateStatutoryConfigurationData(
        configType: 'zimdef', configuration: ['rate' => '0.01'], effectiveFrom: now()->subYear(), createdByUserId: $user->id,
    ));

    $basicComponent = app(CreatePayComponentAction::class)->execute(new CreatePayComponentData(
        schoolId: $school->id, code: 'ABASIC', name: 'Basic Salary', componentType: 'earning',
        category: 'basic', calculationMethod: 'fixed',
    ));

    $glKeys = [
        'payroll_salaries_expense', 'payroll_employer_nssa_expense', 'payroll_employer_apwcs_expense',
        'payroll_zimdef_expense', 'payroll_employer_nec_expense', 'payroll_net_salaries_payable',
        'payroll_paye_payable', 'payroll_aids_levy_payable', 'payroll_nssa_payable', 'payroll_apwcs_payable',
        'payroll_zimdef_payable', 'payroll_nec_payable', 'payroll_staff_loans_receivable', 'payroll_third_party_payables',
    ];

    foreach ($glKeys as $key) {
        Account::factory()->for($school)->expense()->create(['system_key' => $key]);
    }

    $feeDebtorsAccount = Account::factory()->for($school)->controlAccount('student')->create();

    return [
        'school' => $school, 'year' => $year, 'term' => $term, 'user' => $user,
        'basicComponent' => $basicComponent, 'feeDebtorsAccount' => $feeDebtorsAccount,
    ];
}

/**
 * @param  array<string, mixed>  $f
 */
function payrollAdminUser(array $f, string ...$permissionNames): User
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

/**
 * @param  array<string, mixed>  $f
 */
function payrollAdminStaff(array $f): Staff
{
    $staff = app(CreateStaffAction::class)->execute(new CreateStaffData(
        schoolId: $f['school']->id, firstName: 'Admin', lastName: 'Teacher', dateOfBirth: now()->subYears(30),
        gender: 'female', primaryPhone: '+263772345678', staffCategory: 'teaching',
        joinedOn: now()->subYears(2), createdByUserId: $f['user']->id, isTeaching: true,
    ));

    $staff->update(['bank_account_number' => '9988776655', 'nssa_number' => 'NSSA-ADM-01']);

    $structure = app(CreateStaffPayStructureAction::class)->execute(new CreateStaffPayStructureData(
        schoolId: $f['school']->id, staffId: $staff->id, primaryCurrency: 'USD', paymentCurrency: 'USD',
        effectiveFrom: now()->subYear(), approvedByUserId: $f['user']->id,
    ));

    app(AddStaffPayComponentAction::class)->execute(new AddStaffPayComponentData(
        schoolId: $f['school']->id, payStructureId: $structure->id, componentId: $f['basicComponent']->id,
        currency: 'USD', effectiveFrom: now()->subYear(), amountMinor: 200000,
    ));

    return $staff->fresh();
}

it('refuses to mount the payroll run wizard for a user with no payroll.run grant', function (): void {
    $f = payrollAdminFixture();
    $outsider = User::factory()->create();
    $outsider->schools()->attach($f['school'], ['status' => 'active']);

    Livewire::actingAs($outsider)->test(RunWizard::class, ['school' => $f['school']])
        ->assertForbidden();
});

it('renders every payroll screen for a fully-permissioned user', function (): void {
    $f = payrollAdminFixture();
    payrollAdminStaff($f);
    $user = payrollAdminUser(
        $f,
        'payroll.view', 'payroll.manage', 'payroll.staff.manage', 'payroll.statutory.manage',
        'payroll.run', 'payroll.approve', 'payroll.post', 'payroll.pay', 'payroll.loan.manage',
        'payroll.returns.manage', 'payroll.report.view', 'people.staff.view_compensation',
    );

    $run = Livewire::actingAs($user)->test(RunWizard::class, ['school' => $f['school']])
        ->call('compute')
        ->get('selectedRunId');

    $payslip = Payslip::where('payroll_run_id', $run)->first();

    Livewire::actingAs($user)->test(StatutoryConfig::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(GradesIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(ComponentsIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(StaffStructure::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(LoansIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(RunWizard::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(RunBankFile::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(PayslipsShow::class, ['school' => $f['school'], 'payslip' => $payslip])->assertOk();
    Livewire::actingAs($user)->test(ReturnsIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(ReturnsItf16::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(ReportsSummary::class, ['school' => $f['school']])->assertOk();
});

it('reads PAYE from versioned configuration, not a hard-coded rate — changing the configured band changes the computed deduction', function (): void {
    $f = payrollAdminFixture();
    payrollAdminStaff($f);
    $user = payrollAdminUser($f, 'payroll.view', 'payroll.run', 'payroll.statutory.manage');

    $wizard = Livewire::actingAs($user)->test(RunWizard::class, ['school' => $f['school']])
        ->set('payDate', now()->toDateString())
        ->set('periodStart', now()->startOfMonth()->toDateString())
        ->set('periodEnd', now()->endOfMonth()->toDateString())
        ->call('compute');

    $firstRunId = $wizard->get('selectedRunId');
    $firstPaye = (int) Payslip::where('payroll_run_id', $firstRunId)->sum('paye_minor');

    // Supersede the PAYE band table through the real `Statutory\Config`
    // screen itself (not calling the Action directly) — proving the UI
    // path, not just the backend, reads the new rate. Effective from
    // next month, so the first run's period is untouched
    // (BR-PPL-05-004: superseding never alters history).
    $nextMonth = now()->addMonth();

    Livewire::actingAs($user)->test(StatutoryConfig::class, ['school' => $f['school']])
        ->set('configType', 'paye_bands')
        ->set('currency', 'USD')
        ->set('effectiveFrom', $nextMonth->copy()->startOfMonth()->toDateString())
        ->set('configurationJson', json_encode(['bands' => [['from_minor' => 0, 'to_minor' => null, 'rate' => '0.35']]]))
        ->set('requiresConfirmation', false)
        ->call('create')
        ->assertHasNoErrors();

    $secondWizard = Livewire::actingAs($user)->test(RunWizard::class, ['school' => $f['school']])
        ->set('payDate', $nextMonth->copy()->startOfMonth()->toDateString())
        ->set('periodStart', $nextMonth->copy()->startOfMonth()->toDateString())
        ->set('periodEnd', $nextMonth->copy()->endOfMonth()->toDateString())
        ->call('compute');

    $secondRunId = $secondWizard->get('selectedRunId');
    $secondPaye = (int) Payslip::where('payroll_run_id', $secondRunId)->sum('paye_minor');

    expect($firstRunId)->not->toBe($secondRunId)
        ->and($secondPaye)->toBeGreaterThan($firstPaye);
});

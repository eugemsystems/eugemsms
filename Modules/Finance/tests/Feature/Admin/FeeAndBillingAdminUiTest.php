<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\Actions\Documents\CreateNumberingSeriesAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\DataObjects\Documents\CreateNumberingSeriesData;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolSection;
use Modules\Core\Models\Term;
use Modules\Finance\Domain\Actions\ActivateFeeStructureAction;
use Modules\Finance\Domain\Actions\CreateFeeComponentAction;
use Modules\Finance\Domain\Actions\CreateFeeStructureAction;
use Modules\Finance\Domain\DataObjects\ActivateFeeStructureData;
use Modules\Finance\Domain\DataObjects\CreateFeeComponentData;
use Modules\Finance\Domain\DataObjects\CreateFeeStructureData;
use Modules\Finance\Livewire\Billing\History;
use Modules\Finance\Livewire\Billing\Preview;
use Modules\Finance\Livewire\Billing\RunWizard;
use Modules\Finance\Livewire\Fees\AdHocCharge;
use Modules\Finance\Livewire\Fees\Components as FeeComponentsScreen;
use Modules\Finance\Livewire\Fees\LearnerDetail;
use Modules\Finance\Livewire\Fees\Simulator;
use Modules\Finance\Livewire\Fees\StructureBuilder;
use Modules\Finance\Livewire\Fees\Structures;
use Modules\Finance\Livewire\Fees\StructureVersions;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\AdHocCharge as AdHocChargeModel;
use Modules\Finance\Models\BillingRun;
use Modules\Finance\Models\FeeComponent;
use Modules\Finance\Models\FeeStructure;
use Modules\Finance\Models\LearnerFeeAssignment;
use Modules\People\Domain\Actions\CreateStudentAction;
use Modules\People\Domain\DataObjects\CreateStudentData;
use Modules\People\Models\Student;

/**
 * Book B FIN-02 §7 admin UI. Own, distinctly-named fixture — see
 * `GeneralLedgerAdminUiTest`'s own note on why a Pest helper defined in
 * one test file can't be relied on from another run standalone.
 *
 * @return array{school: School, year: AcademicYear, term: Term, section: SchoolSection, gradeLevel: GradeLevel, tuition: FeeComponent}
 */
function feeAdminFixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->current()->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->current()->create();
    $section = SchoolSection::factory()->for($school)->create();
    $gradeLevel = GradeLevel::factory()->for($school)->for($section, 'section')->create();

    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
        schoolId: $school->id, documentType: 'admission', pattern: '{SCHOOL}/{YEAR}/{SEQ:4}', academicYearId: $year->id,
    ));
    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
        schoolId: $school->id, documentType: 'journal', pattern: 'JNL/{SEQ:6}',
    ));
    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
        schoolId: $school->id, documentType: 'invoice', pattern: 'INV/{SEQ:6}',
    ));
    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
        schoolId: $school->id, documentType: 'credit_note', pattern: 'CN/{SEQ:6}',
    ));

    $bootstrapUser = User::factory()->create();
    $income = Account::factory()->for($school)->income()->create();
    $debtor = Account::factory()->for($school)->controlAccount('student')->create();

    $tuition = app(CreateFeeComponentAction::class)->execute(new CreateFeeComponentData(
        schoolId: $school->id, code: 'TUITION', name: 'Tuition', category: 'tuition',
        incomeAccountId: $income->id, debtorAccountId: $debtor->id, defaultCurrency: 'USD', createdByUserId: $bootstrapUser->id,
    ));

    return ['school' => $school, 'year' => $year, 'term' => $term, 'section' => $section, 'gradeLevel' => $gradeLevel, 'tuition' => $tuition];
}

function feeAdminUser(School $school, string ...$permissionNames): User
{
    $user = User::factory()->create();
    $user->schools()->attach($school, ['status' => 'active']);

    $grants = array_map(function (string $permissionName): PermissionGrantData {
        [$moduleCode, $resource, $action] = explode('.', $permissionName);

        $permission = Permission::firstOrCreate(
            ['name' => $permissionName],
            ['guard_name' => 'web', 'module_code' => strtoupper($moduleCode), 'resource' => $resource, 'action' => $action],
        );

        return new PermissionGrantData($permission->id, PermissionScope::School);
    }, $permissionNames);

    if ($grants !== []) {
        app(UpdateUserPermissionsAction::class)->execute(new UserPermissionData(
            userId: $user->id,
            schoolId: $school->id,
            grants: $grants,
        ));
    }

    return $user;
}

/**
 * @param  array{school: School, year: AcademicYear, term: Term, section: SchoolSection, gradeLevel: GradeLevel, tuition: FeeComponent}  $f
 */
function feeAdminStudent(array $f, User $createdBy, array $overrides = []): Student
{
    return app(CreateStudentAction::class)->execute(new CreateStudentData(
        schoolId: $f['school']->id,
        academicYearId: $f['year']->id,
        termId: $f['term']->id,
        firstName: $overrides['firstName'] ?? 'Rutendo',
        lastName: $overrides['lastName'] ?? 'Chiweshe',
        dateOfBirth: now()->subYears(16),
        gender: 'female',
        enrolmentType: $overrides['enrolmentType'] ?? 'FULL_TIME',
        residency: 'DAY',
        sectionId: $overrides['sectionId'] ?? $f['section']->id,
        gradeLevelId: $overrides['gradeLevelId'] ?? $f['gradeLevel']->id,
        entryCohortYear: (int) now()->year,
        createdByUserId: $createdBy->id,
        skipDuplicateCheck: true,
    ));
}

beforeEach(function (): void {
    if (! Route::has('finance.fees.components')) {
        require base_path('Modules/Finance/routes/billing.php');
    }
});

it('creates a fee component (Book B FIN-02 §2/§7)', function (): void {
    $f = feeAdminFixture();
    $user = feeAdminUser($f['school'], 'finance.fee_component.manage');
    $income = Account::factory()->for($f['school'])->income()->create();
    $debtor = Account::factory()->for($f['school'])->controlAccount('student')->create();

    Livewire::actingAs($user)
        ->test(FeeComponentsScreen::class, ['school' => $f['school']])
        ->call('openCreateModal')
        ->set('code', 'LEVY')
        ->set('name', 'Development Levy')
        ->set('category', 'levy')
        ->set('incomeAccountId', $income->id)
        ->set('debtorAccountId', $debtor->id)
        ->call('create')
        ->assertHasNoErrors();

    expect(FeeComponent::where('school_id', $f['school']->id)->where('code', 'LEVY')->exists())->toBeTrue();
});

it('lists fee structures', function (): void {
    $f = feeAdminFixture();
    $user = feeAdminUser($f['school'], 'finance.fee_structure.view');

    app(CreateFeeStructureAction::class)->execute(new CreateFeeStructureData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, name: 'Full-Time Structure', priority: 10,
        rules: [['attribute' => 'enrolment_type', 'operator' => 'equals', 'value' => 'FULL_TIME']],
        items: [['component_id' => $f['tuition']->id, 'billing_basis' => 'flat_per_term', 'currency' => 'USD', 'amount_minor' => 10000]],
        createdByUserId: $user->id,
    ));

    Livewire::actingAs($user)
        ->test(Structures::class, ['school' => $f['school']])
        ->assertSee('Full-Time Structure');
});

it('creates a new fee structure with a live match count, then revises it into a new version (BR-FIN-02-001/011)', function (): void {
    $f = feeAdminFixture();
    $user = feeAdminUser($f['school'], 'finance.fee_structure.manage');
    feeAdminStudent($f, $user, ['firstName' => 'Tinashe']);
    feeAdminStudent($f, $user, ['firstName' => 'Farai', 'enrolmentType' => 'PART_TIME']);

    $component = FeeComponent::where('school_id', $f['school']->id)->sole();

    Livewire::actingAs($user)
        ->test(StructureBuilder::class, ['school' => $f['school']])
        ->set('name', 'Full-Time Structure')
        ->set('academicYearId', $f['year']->id)
        ->set('termId', $f['term']->id)
        ->set('rules.0.attribute', 'enrolment_type')
        ->set('rules.0.operator', 'equals')
        ->set('rules.0.value', 'FULL_TIME')
        ->call('previewMatchCount')
        ->assertSet('matchCount', 1)
        ->set('items.0.component_id', (string) $component->id)
        ->set('items.0.billing_basis', 'flat_per_term')
        ->set('items.0.amount_minor', '150.00')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $structure = FeeStructure::where('school_id', $f['school']->id)->where('version', 1)->sole();
    expect($structure->status)->toBe('draft')
        ->and($structure->items->first()->amount_minor)->toBe(15000);

    app(ActivateFeeStructureAction::class)->execute(new ActivateFeeStructureData($structure->id, $user->id));

    Livewire::actingAs($user)
        ->test(StructureBuilder::class, ['school' => $f['school'], 'structure' => $structure->fresh()])
        ->set('items.0.amount_minor', '175.00')
        ->call('save')
        ->assertHasNoErrors();

    $revision = FeeStructure::where('school_id', $f['school']->id)->where('version', 2)->sole();
    expect($revision->status)->toBe('draft')
        ->and($revision->items->first()->amount_minor)->toBe(17500)
        ->and($structure->fresh()->status)->toBe('active');
});

it('shows a diff between two structure versions', function (): void {
    $f = feeAdminFixture();
    $user = feeAdminUser($f['school'], 'finance.fee_structure.view');

    $v1 = app(CreateFeeStructureAction::class)->execute(new CreateFeeStructureData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id, name: 'Full-Time Structure', priority: 10,
        rules: [['attribute' => 'enrolment_type', 'operator' => 'equals', 'value' => 'FULL_TIME']],
        items: [['component_id' => $f['tuition']->id, 'billing_basis' => 'flat_per_term', 'currency' => 'USD', 'amount_minor' => 10000]],
        createdByUserId: $user->id,
    ));

    Livewire::actingAs($user)
        ->test(StructureVersions::class, ['school' => $f['school'], 'structure' => $v1])
        ->assertSee('v1')
        ->assertSee('TUITION');
});

it('shows a learner\'s fee assignment lines and resolution trace', function (): void {
    $f = feeAdminFixture();
    $user = feeAdminUser($f['school'], 'finance.fee_structure.manage', 'finance.billing.run', 'finance.fee.view');
    $structure = app(CreateFeeStructureAction::class)->execute(new CreateFeeStructureData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, name: 'Full-Time Structure', priority: 10,
        rules: [['attribute' => 'enrolment_type', 'operator' => 'equals', 'value' => 'FULL_TIME']],
        items: [['component_id' => $f['tuition']->id, 'billing_basis' => 'flat_per_term', 'currency' => 'USD', 'amount_minor' => 10000]],
        createdByUserId: $user->id,
    ));
    app(ActivateFeeStructureAction::class)->execute(new ActivateFeeStructureData($structure->id, $user->id));
    $student = feeAdminStudent($f, $user);

    Livewire::actingAs($user)
        ->test(RunWizard::class, ['school' => $f['school']])
        ->call('compute')
        ->assertHasNoErrors();

    $assignment = LearnerFeeAssignment::where('student_id', $student->id)->sole();

    Livewire::actingAs($user)
        ->test(LearnerDetail::class, ['school' => $f['school'], 'student' => $student])
        ->assertSee('Tuition')
        ->call('toggleTrace', $assignment->id)
        ->assertSee('structures_evaluated');
});

it('raises an individual ad hoc charge and skips one above the approval threshold without an approver (BR-FIN-02-019)', function (): void {
    $f = feeAdminFixture();
    $user = feeAdminUser($f['school'], 'finance.ad_hoc.create');
    $student = feeAdminStudent($f, $user);

    Livewire::actingAs($user)
        ->test(AdHocCharge::class, ['school' => $f['school']])
        ->call('selectStudent', $student->id)
        ->set('componentId', $f['tuition']->id)
        ->set('description', 'Replacement textbook')
        ->set('unitRate', '10.00')
        ->call('raise')
        ->assertHasNoErrors();

    expect(AdHocChargeModel::where('student_id', $student->id)->where('status', 'pending')->exists())->toBeTrue();

    Livewire::actingAs($user)
        ->test(AdHocCharge::class, ['school' => $f['school']])
        ->call('selectStudent', $student->id)
        ->set('componentId', $f['tuition']->id)
        ->set('description', 'Damaged laptop cart fee')
        ->set('unitRate', '999.00')
        ->call('raise')
        ->assertDispatched('toast', variant: 'warning');

    expect(AdHocChargeModel::where('student_id', $student->id)->where('unit_rate_minor', 99900)->exists())->toBeFalse();
});

it('lets a user holding finance.ad_hoc.approve self-approve a charge above the threshold', function (): void {
    $f = feeAdminFixture();
    $user = feeAdminUser($f['school'], 'finance.ad_hoc.create', 'finance.ad_hoc.approve');
    $student = feeAdminStudent($f, $user);

    Livewire::actingAs($user)
        ->test(AdHocCharge::class, ['school' => $f['school']])
        ->call('selectStudent', $student->id)
        ->set('componentId', $f['tuition']->id)
        ->set('description', 'Damaged laptop cart fee')
        ->set('unitRate', '999.00')
        ->set('selfApprove', true)
        ->call('raise')
        ->assertDispatched('toast', variant: 'success');

    $charge = AdHocChargeModel::where('student_id', $student->id)->sole();
    expect($charge->approved_by)->toBe($user->id);
});

it('simulates an indicative fee for a real existing learner (BR-FIN-02, fee simulator)', function (): void {
    $f = feeAdminFixture();
    $user = feeAdminUser($f['school'], 'finance.fee_structure.view', 'finance.fee_structure.manage');
    $structure = app(CreateFeeStructureAction::class)->execute(new CreateFeeStructureData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, name: 'Full-Time Structure', priority: 10,
        rules: [['attribute' => 'enrolment_type', 'operator' => 'equals', 'value' => 'FULL_TIME']],
        items: [['component_id' => $f['tuition']->id, 'billing_basis' => 'flat_per_term', 'currency' => 'USD', 'amount_minor' => 10000]],
        createdByUserId: $user->id,
    ));
    app(ActivateFeeStructureAction::class)->execute(new ActivateFeeStructureData($structure->id, $user->id));
    $student = feeAdminStudent($f, $user);

    Livewire::actingAs($user)
        ->test(Simulator::class, ['school' => $f['school']])
        ->call('selectStudent', $student->id)
        ->set('termId', $f['term']->id)
        ->call('preview')
        ->assertHasNoErrors()
        ->assertSee('100.00');
});

it('computes a scoped billing run and walks it through approve and commit (BR-FIN-02-013)', function (): void {
    $f = feeAdminFixture();
    $creator = feeAdminUser($f['school'], 'finance.billing.run');
    $approver = feeAdminUser($f['school'], 'finance.billing.run', 'finance.billing.approve');
    $committer = feeAdminUser($f['school'], 'finance.billing.run', 'finance.billing.commit');

    $structure = app(CreateFeeStructureAction::class)->execute(new CreateFeeStructureData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, name: 'Full-Time Structure', priority: 10,
        rules: [['attribute' => 'enrolment_type', 'operator' => 'equals', 'value' => 'FULL_TIME']],
        items: [['component_id' => $f['tuition']->id, 'billing_basis' => 'flat_per_term', 'currency' => 'USD', 'amount_minor' => 10000]],
        createdByUserId: $creator->id,
    ));
    app(ActivateFeeStructureAction::class)->execute(new ActivateFeeStructureData($structure->id, $creator->id));

    $inScope = feeAdminStudent($f, $creator, ['firstName' => 'InScope']);
    $outOfScopeSection = SchoolSection::factory()->for($f['school'])->create(['code' => 'SEN']);
    $outOfScopeGrade = GradeLevel::factory()->for($f['school'])->for($outOfScopeSection, 'section')->create();
    feeAdminStudent($f, $creator, ['firstName' => 'OutOfScope', 'sectionId' => $outOfScopeSection->id, 'gradeLevelId' => $outOfScopeGrade->id]);

    Livewire::actingAs($creator)
        ->test(RunWizard::class, ['school' => $f['school']])
        ->set('sectionId', $f['section']->id)
        ->call('compute')
        ->assertHasNoErrors();

    $run = BillingRun::where('school_id', $f['school']->id)->sole();
    expect($run->scope_filter)->toBe(['section_id' => $f['section']->id])
        ->and($run->computed_count)->toBe(1)
        ->and(LearnerFeeAssignment::where('billing_run_id', $run->id)->where('student_id', $inScope->id)->exists())->toBeTrue();

    Livewire::actingAs($approver)
        ->test(Preview::class, ['school' => $f['school'], 'billingRun' => $run])
        ->call('approve')
        ->assertDispatched('toast', variant: 'success');

    expect($run->fresh()->status)->toBe('approved');

    Livewire::actingAs($committer)
        ->test(Preview::class, ['school' => $f['school'], 'billingRun' => $run->fresh()])
        ->call('commit')
        ->assertDispatched('toast', variant: 'success');

    expect($run->fresh()->status)->toBe('committed');
});

it('lists billing run history', function (): void {
    $f = feeAdminFixture();
    $user = feeAdminUser($f['school'], 'finance.billing.view', 'finance.billing.run');

    Livewire::actingAs($user)
        ->test(RunWizard::class, ['school' => $f['school']])
        ->call('compute');

    Livewire::actingAs($user)
        ->test(History::class, ['school' => $f['school']])
        ->assertOk()
        ->assertSee('#');
});

/**
 * Real routed GET, not `Livewire::test()` — see the identical test in
 * `GeneralLedgerAdminUiTest` for why this matters specifically for a
 * class literally named `Index`. FIN-02 has no `Index`-named component
 * of its own, but `Fees\Components` still hits the same
 * implicit-binding-substitution step on a real route, so it's worth
 * proving end to end too.
 */
it('serves Finance\Fees\Components through a real routed request', function (): void {
    $f = feeAdminFixture();
    $user = feeAdminUser($f['school'], 'finance.fee_component.manage');

    $this->actingAs($user)->get(route('finance.fees.components', $f['school']))->assertOk();
});

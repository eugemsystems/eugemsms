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
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolSection;
use Modules\Core\Models\Term;
use Modules\Finance\Models\Account;
use Modules\People\Domain\Actions\CreateIntakeAction;
use Modules\People\Domain\Actions\OfferApplicationAction;
use Modules\People\Domain\Actions\SubmitApplicationAction;
use Modules\People\Domain\DataObjects\CreateIntakeData;
use Modules\People\Domain\DataObjects\OfferApplicationData;
use Modules\People\Domain\DataObjects\SubmitApplicationData;
use Modules\People\Livewire\Admissions\Applications\Convert as ApplicationsConvert;
use Modules\People\Livewire\Admissions\Applications\Create as ApplicationsCreate;
use Modules\People\Livewire\Admissions\Applications\Show as ApplicationsShow;
use Modules\People\Livewire\Admissions\Intakes\Index as IntakesIndex;
use Modules\People\Models\Application;
use Modules\People\Models\Intake;
use Modules\People\Models\Student;
use Modules\People\Models\StudentGuardian;

/**
 * Book C PPL-02 §5 admin UI — Admissions & Enrolment CRM. Own,
 * distinctly-named fixture — see `StudentsAdminUiTest`'s own note on
 * why a Pest helper defined in one test file can't be relied on from
 * another run standalone. The domain layer's own lifecycle is already
 * exhaustively covered by `Ppl02AdmissionsTest`; this file only proves
 * the admin screens wire into those same Actions correctly.
 *
 * @return array{school: School, year: AcademicYear, term: Term, section: SchoolSection, gradeLevel: GradeLevel, user: User, bankAccount: Account, incomeAccount: Account, refundableDeposits: Account, creditBalanceAccount: Account}
 */
function admissionsAdminFixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->current()->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->current()->create();
    $section = SchoolSection::factory()->for($school)->create();
    $gradeLevel = GradeLevel::factory()->for($school)->for($section, 'section')->create();
    $user = User::factory()->create();

    foreach (['admission' => 'ADM', 'application' => 'APP', 'receipt' => 'RCT', 'journal' => 'JNL'] as $documentType => $prefix) {
        app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
            schoolId: $school->id, documentType: $documentType, pattern: $prefix.'/{SEQ:6}', academicYearId: $year->id,
        ));
    }

    return [
        'school' => $school, 'year' => $year, 'term' => $term, 'section' => $section, 'gradeLevel' => $gradeLevel,
        'user' => $user,
        'bankAccount' => Account::factory()->for($school)->create(),
        'incomeAccount' => Account::factory()->for($school)->income()->create(),
        'refundableDeposits' => Account::factory()->for($school)->create(),
        'creditBalanceAccount' => Account::factory()->for($school)->system('credit_balance')->create(),
    ];
}

/**
 * @param  array<string, mixed>  $f
 */
function admissionsAdminUser(array $f, string ...$permissionNames): User
{
    $user = User::factory()->create();
    $user->schools()->attach($f['school'], ['status' => 'active']);

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
            userId: $user->id, schoolId: $f['school']->id, grants: $grants,
        ));
    }

    return $user;
}

/**
 * @param  array<string, mixed>  $f
 */
function createAdmissionsIntake(array $f, ?int $applicationFeeMinor = null): Intake
{
    return app(CreateIntakeAction::class)->execute(new CreateIntakeData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, name: 'Form 1 Intake',
        gradeLevelId: $f['gradeLevel']->id, opensOn: now()->subMonth(), closesOn: now()->addMonth(),
        targetPlaces: 5, createdByUserId: $f['user']->id,
        applicationFeeMinor: $applicationFeeMinor, applicationFeeCurrency: $applicationFeeMinor !== null ? 'USD' : null,
        acceptanceDepositMinor: 20000, acceptanceDepositCurrency: 'USD',
    ));
}

it('serves Intakes\\Index through a real routed request', function (): void {
    $f = admissionsAdminFixture();
    $user = admissionsAdminUser($f, 'people.admissions.intake_manage');

    $this->actingAs($user)
        ->get(route('people.admissions.intakes.index', $f['school']))
        ->assertOk();
});

it('refuses Intakes\\Index to a user without people.admissions.intake_manage', function (): void {
    $f = admissionsAdminFixture();
    $user = admissionsAdminUser($f);

    Livewire::actingAs($user)->test(IntakesIndex::class, ['school' => $f['school']])
        ->assertForbidden();
});

it('creates an intake', function (): void {
    $f = admissionsAdminFixture();
    $user = admissionsAdminUser($f, 'people.admissions.intake_manage');

    Livewire::actingAs($user)->test(IntakesIndex::class, ['school' => $f['school']])
        ->set('academicYearId', $f['year']->id)
        ->set('name', 'Form 1 Intake 2027')
        ->set('gradeLevelId', $f['gradeLevel']->id)
        ->set('targetPlaces', '30')
        ->call('create');

    expect(Intake::where('school_id', $f['school']->id)->where('name', 'Form 1 Intake 2027')->exists())->toBeTrue();
});

it('captures a new application with a guardian through the Create screen', function (): void {
    $f = admissionsAdminFixture();
    $intake = createAdmissionsIntake($f);
    $user = admissionsAdminUser($f, 'people.admissions.application_create');

    Livewire::actingAs($user)->test(ApplicationsCreate::class, ['school' => $f['school']])
        ->set('intakeId', $intake->id)
        ->set('firstName', 'Tadiwa')
        ->set('lastName', 'Moyo')
        ->set('dateOfBirth', now()->subYears(12)->toDateString())
        ->set('requestedGradeLevelId', $f['gradeLevel']->id)
        ->set('guardians.0.relationship', 'mother')
        ->set('guardians.0.first_name', 'Grace')
        ->set('guardians.0.last_name', 'Moyo')
        ->set('guardians.0.primary_phone', '+263771234567')
        ->set('guardians.0.is_fee_responsible', true)
        ->call('save')
        ->assertRedirect();

    $application = Application::where('school_id', $f['school']->id)->where('first_name', 'Tadiwa')->sole();
    expect($application->status)->toBe('submitted')
        ->and($application->guardians()->where('primary_phone', '+263771234567')->exists())->toBeTrue();
});

it('progresses a fee-pending application through fee payment on the Show screen (BR-PPL-02-003)', function (): void {
    $f = admissionsAdminFixture();
    $intake = createAdmissionsIntake($f, applicationFeeMinor: 1000);
    $application = app(SubmitApplicationAction::class)->execute(new SubmitApplicationData(
        schoolId: $f['school']->id, intakeId: $intake->id, firstName: 'Tadiwa', lastName: 'Moyo',
        dateOfBirth: now()->subYears(12), gender: 'male', requestedGradeLevelId: $f['gradeLevel']->id,
        requestedEnrolmentType: 'FULL_TIME', requestedResidency: 'DAY',
        guardians: [['relationship' => 'mother', 'first_name' => 'Grace', 'last_name' => 'Moyo', 'primary_phone' => '+263771234567', 'is_fee_responsible' => true]],
        createdByUserId: $f['user']->id,
    ));
    expect($application->status)->toBe('fee_pending');

    $user = admissionsAdminUser($f, 'people.admissions.application_view', 'people.admissions.application_review');

    Livewire::actingAs($user)->test(ApplicationsShow::class, ['school' => $f['school'], 'application' => $application])
        ->set('feeBankAccountId', $f['bankAccount']->id)
        ->set('feeIncomeAccountId', $f['incomeAccount']->id)
        ->call('payFee');

    expect($application->fresh()->status)->toBe('submitted');
});

it('refuses making an offer without people.admissions.offer_make even with application_review granted', function (): void {
    $f = admissionsAdminFixture();
    $intake = createAdmissionsIntake($f);
    $application = app(SubmitApplicationAction::class)->execute(new SubmitApplicationData(
        schoolId: $f['school']->id, intakeId: $intake->id, firstName: 'Tadiwa', lastName: 'Moyo',
        dateOfBirth: now()->subYears(12), gender: 'male', requestedGradeLevelId: $f['gradeLevel']->id,
        requestedEnrolmentType: 'FULL_TIME', requestedResidency: 'DAY',
        guardians: [['relationship' => 'mother', 'first_name' => 'Grace', 'last_name' => 'Moyo', 'is_fee_responsible' => true]],
        createdByUserId: $f['user']->id,
    ));

    $user = admissionsAdminUser($f, 'people.admissions.application_view', 'people.admissions.application_review');

    Livewire::actingAs($user)->test(ApplicationsShow::class, ['school' => $f['school'], 'application' => $application])
        ->call('makeOffer')
        ->assertForbidden();
});

it('runs the full pipeline from offer to conversion through the admin screens', function (): void {
    $f = admissionsAdminFixture();
    $intake = createAdmissionsIntake($f);
    $application = app(SubmitApplicationAction::class)->execute(new SubmitApplicationData(
        schoolId: $f['school']->id, intakeId: $intake->id, firstName: 'Tadiwa', lastName: 'Moyo',
        dateOfBirth: now()->subYears(12), gender: 'male', requestedGradeLevelId: $f['gradeLevel']->id,
        requestedEnrolmentType: 'FULL_TIME', requestedResidency: 'DAY',
        guardians: [['relationship' => 'mother', 'first_name' => 'Grace', 'last_name' => 'Moyo', 'primary_phone' => '+263771234567', 'is_fee_responsible' => true]],
        createdByUserId: $f['user']->id,
    ));

    $user = admissionsAdminUser(
        $f,
        'people.admissions.application_view', 'people.admissions.application_review',
        'people.admissions.offer_make', 'people.admissions.convert',
    );

    $component = Livewire::actingAs($user)->test(ApplicationsShow::class, ['school' => $f['school'], 'application' => $application])
        ->call('makeOffer');
    expect($application->fresh()->status)->toBe('offered');

    $component->call('acceptOffer');
    expect($application->fresh()->status)->toBe('accepted');

    $component->set('depositBankAccountId', $f['bankAccount']->id)
        ->set('depositRefundableAccountId', $f['refundableDeposits']->id)
        ->call('payDeposit');
    expect($application->fresh()->status)->toBe('deposit_paid');

    Livewire::actingAs($user)->test(ApplicationsConvert::class, ['school' => $f['school'], 'application' => $application->fresh()])
        ->call('convert')
        ->assertRedirect();

    $application = $application->fresh();
    expect($application->status)->toBe('enrolled')
        ->and($application->student_id)->not->toBeNull();

    $student = Student::find($application->student_id);
    expect($student->first_name)->toBe('Tadiwa')
        ->and(StudentGuardian::where('student_id', $student->id)->where('is_fee_responsible', true)->exists())->toBeTrue();
});

it('declines an application from the Show screen and releases an offered place', function (): void {
    $f = admissionsAdminFixture();
    $intake = createAdmissionsIntake($f);
    $application = app(SubmitApplicationAction::class)->execute(new SubmitApplicationData(
        schoolId: $f['school']->id, intakeId: $intake->id, firstName: 'Tadiwa', lastName: 'Moyo',
        dateOfBirth: now()->subYears(12), gender: 'male', requestedGradeLevelId: $f['gradeLevel']->id,
        requestedEnrolmentType: 'FULL_TIME', requestedResidency: 'DAY',
        guardians: [['relationship' => 'mother', 'first_name' => 'Grace', 'last_name' => 'Moyo', 'is_fee_responsible' => true]],
        createdByUserId: $f['user']->id,
    ));
    app(OfferApplicationAction::class)->execute(new OfferApplicationData(applicationId: $application->id, offeredByUserId: $f['user']->id));

    $user = admissionsAdminUser($f, 'people.admissions.application_view', 'people.admissions.application_review');

    Livewire::actingAs($user)->test(ApplicationsShow::class, ['school' => $f['school'], 'application' => $application->fresh()])
        ->set('declineReason', 'Family relocated')
        ->call('declineApplication');

    expect($application->fresh()->status)->toBe('declined')
        ->and($intake->fresh()->places_offered)->toBe(0);
});

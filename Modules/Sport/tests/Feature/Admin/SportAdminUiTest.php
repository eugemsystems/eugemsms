<?php

use App\Models\User;
use Illuminate\Support\Carbon;
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
use Modules\People\Domain\Actions\CreateStudentAction;
use Modules\People\Domain\DataObjects\CreateStudentData;
use Modules\Sport\Domain\Actions\CreateActivityAction;
use Modules\Sport\Domain\Actions\CreateTeamAction;
use Modules\Sport\Domain\Actions\ScheduleFixtureAction;
use Modules\Sport\Domain\DataObjects\CreateActivityData;
use Modules\Sport\Domain\DataObjects\CreateTeamData;
use Modules\Sport\Domain\DataObjects\ScheduleFixtureData;
use Modules\Sport\Livewire\Activities\Index as ActivitiesIndex;
use Modules\Sport\Livewire\Awards\Index as AwardsIndex;
use Modules\Sport\Livewire\Equipment\Index as EquipmentIndex;
use Modules\Sport\Livewire\Fixtures\Index as FixturesIndex;
use Modules\Sport\Livewire\Houses\Leaderboard as HousesLeaderboard;
use Modules\Sport\Livewire\Membership\Index as MembershipIndex;
use Modules\Sport\Livewire\Teams\Index as TeamsIndex;
use Modules\Sport\Models\Fixture;
use Modules\Welfare\Domain\Actions\DeclareMedicalConditionAction;
use Modules\Welfare\Domain\DataObjects\DeclareMedicalConditionData;

/**
 * Book H2 OPS-07 admin-UI pass. Own, distinctly-named fixture.
 *
 * @return array{school: School, year: AcademicYear, term: Term, user: User}
 */
function sportAdminFixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->current()->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->current()->create(['financial_state' => 'open']);
    $user = User::factory()->create();
    $user->schools()->attach($school, ['status' => 'active']);

    return compact('school', 'year', 'term', 'user');
}

/**
 * @param  array<string, mixed>  $f
 */
function sportAdminUser(array $f, string ...$permissionNames): User
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

it('refuses to mount the fixtures screen for a user with no activities.fixture.manage grant', function (): void {
    $f = sportAdminFixture();
    $outsider = User::factory()->create();
    $outsider->schools()->attach($f['school'], ['status' => 'active']);

    Livewire::actingAs($outsider)->test(FixturesIndex::class, ['school' => $f['school']])
        ->assertForbidden();
});

it('renders every sport screen for a fully-permissioned user', function (): void {
    $f = sportAdminFixture();
    $user = sportAdminUser(
        $f,
        'activities.manage', 'activities.team.manage', 'activities.fixture.manage',
        'activities.view', 'activities.award.manage',
    );

    Livewire::actingAs($user)->test(ActivitiesIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(MembershipIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(TeamsIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(FixturesIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(HousesLeaderboard::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(AwardsIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(EquipmentIndex::class, ['school' => $f['school']])->assertOk();
});

it('blocks squad selection for a learner with an uncleared medical condition affecting physical activity (AC-OPS-07-001)', function (): void {
    $f = sportAdminFixture();
    $manager = sportAdminUser($f, 'activities.manage', 'activities.team.manage', 'activities.fixture.manage');

    $activity = app(CreateActivityAction::class)->execute(new CreateActivityData(
        schoolId: $f['school']->id, code: 'RUGBY', name: 'Rugby', activityType: 'sport',
        requiresMedicalClearance: true,
    ));

    $team = app(CreateTeamAction::class)->execute(new CreateTeamData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, activityId: $activity->id, name: '1st XV',
    ));

    $fixture = app(ScheduleFixtureAction::class)->execute(new ScheduleFixtureData(
        schoolId: $f['school']->id, termId: $f['term']->id, teamId: $team->id, opponent: 'Rival School',
        fixtureType: 'friendly', venueType: 'home', fixtureDate: Carbon::now()->addWeek(),
    ));

    $section = SchoolSection::factory()->for($f['school'])->create();
    $gradeLevel = GradeLevel::factory()->for($f['school'])->for($section, 'section')->create();

    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
        schoolId: $f['school']->id, documentType: 'admission', pattern: '{SCHOOL}/{YEAR}/{SEQ:4}', academicYearId: $f['year']->id,
    ));

    $student = app(CreateStudentAction::class)->execute(new CreateStudentData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        firstName: 'Tendai', lastName: 'Moyo', dateOfBirth: Carbon::now()->subYears(15), gender: 'male',
        enrolmentType: 'FULL_TIME', residency: 'DAY', sectionId: $section->id, gradeLevelId: $gradeLevel->id,
        entryCohortYear: (int) Carbon::now()->year,
        createdByUserId: $manager->id, skipDuplicateCheck: true,
    ));

    app(DeclareMedicalConditionAction::class)->execute(new DeclareMedicalConditionData(
        schoolId: $f['school']->id, studentId: $student->id, conditionType: 'asthma',
        name: 'Exercise-induced asthma', severity: 'moderate', declaredBy: 'guardian',
        effectiveFrom: Carbon::now(), affectsPhysicalActivity: true,
        createdByUserId: $manager->id,
    ));

    Livewire::actingAs($manager)->test(FixturesIndex::class, ['school' => $f['school']])
        ->call('select', $fixture->id)
        ->set('squadStudentIds', [$student->id])
        ->call('selectSquad')
        ->assertOk();

    expect(Fixture::find($fixture->id)->squad_student_ids)->toBeNull();
});

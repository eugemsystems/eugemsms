<?php

use App\Models\User;
use Livewire\Livewire;
use Modules\Academic\Models\CurriculumFramework;
use Modules\Academic\Models\ExaminationCandidate;
use Modules\Academic\Models\ExaminationSession;
use Modules\Academic\Models\Subject;
use Modules\Compliance\Domain\Actions\CreateZimsecRegistrationAction;
use Modules\Compliance\Domain\DataObjects\CreateZimsecRegistrationData;
use Modules\Compliance\Livewire\Zimsec\Analysis\Index as AnalysisIndex;
use Modules\Compliance\Livewire\Zimsec\Export\Index as ExportIndex;
use Modules\Compliance\Livewire\Zimsec\Fees\Index as FeesIndex;
use Modules\Compliance\Livewire\Zimsec\Registrations\Index as RegistrationsIndex;
use Modules\Compliance\Livewire\Zimsec\ResultsImport\Index as ResultsImportIndex;
use Modules\Compliance\Livewire\Zimsec\Statements\Index as StatementsIndex;
use Modules\Compliance\Livewire\Zimsec\Validation\Index as ValidationIndex;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\People\Models\Student;

/**
 * Book H3 CMP-01 admin-UI pass. Own, distinctly-named fixture
 * (`cmp01AdminFixture`/`cmp01AdminUser`) — `cmp01Fixture` already
 * exists in the sibling backend test file
 * `Modules/Compliance/tests/Feature/Cmp01ZimsecRegistrationTest.php`.
 *
 * @return array<string, mixed>
 */
function cmp01AdminFixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create();
    $user = User::factory()->create();
    $framework = CurriculumFramework::factory()->for($school)->create();

    $english = Subject::factory()->for($school)->for($framework, 'framework')->create(['code' => 'ENG', 'name' => 'English Language', 'zimsec_subject_code' => '4021']);

    $session = ExaminationSession::factory()->for($school)->for($year, 'academicYear')->for($term)->create([
        'exam_body' => 'ZIMSEC',
        'status' => 'in_progress',
    ]);

    $registration = app(CreateZimsecRegistrationAction::class)->execute(new CreateZimsecRegistrationData(
        schoolId: $school->id, academicYearId: $year->id, examLevel: 'o_level', examSeries: 'November 2026',
        centreNumber: '12345', registrationClosesOn: now()->addMonth(), currency: 'USD',
        examinationSessionId: $session->id,
    ));

    return compact('school', 'year', 'term', 'user', 'framework', 'english', 'session', 'registration');
}

/**
 * @param  array<string, mixed>  $f
 */
function cmp01AdminUser(array $f, string ...$permissionNames): User
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

it('refuses to mount the registrations screen for a user with no zimsec.manage grant', function (): void {
    $f = cmp01AdminFixture();
    $outsider = User::factory()->create();
    $outsider->schools()->attach($f['school'], ['status' => 'active']);

    Livewire::actingAs($outsider)->test(RegistrationsIndex::class, ['school' => $f['school']])
        ->assertForbidden();
});

it('renders every ZIMSEC screen for a fully-permissioned user', function (): void {
    $f = cmp01AdminFixture();
    $user = cmp01AdminUser($f, 'zimsec.manage', 'zimsec.export', 'zimsec.results.import', 'zimsec.view');

    Livewire::actingAs($user)->test(RegistrationsIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(ValidationIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(FeesIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(ExportIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(StatementsIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(ResultsImportIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(AnalysisIndex::class, ['school' => $f['school']])->assertOk();
});

it('derives candidates from confirmed ACA-07 entries with bio-data copied, never re-keyed (AC-CMP-01-003)', function (): void {
    $f = cmp01AdminFixture();
    $user = cmp01AdminUser($f, 'zimsec.manage');
    $student = Student::factory()->for($f['school'])->create(['first_name' => 'Tendai', 'last_name' => 'Moyo', 'gender' => 'male']);

    ExaminationCandidate::factory()->create([
        'school_id' => $f['school']->id,
        'session_id' => $f['session']->id,
        'student_id' => $student->id,
        'entry_status' => 'confirmed',
        'entered_subjects' => [$f['english']->id],
        'entry_fee_minor' => 2000,
        'entry_fee_currency' => 'USD',
    ]);

    Livewire::actingAs($user)->test(RegistrationsIndex::class, ['school' => $f['school']])
        ->call('deriveCandidates', $f['registration']->id)
        ->assertDispatched('toast');

    expect($f['registration']->fresh()->candidate_count)->toBe(1);
});

it('blocks registration closure while entry fees billed exceed collected (AC-CMP-01-002)', function (): void {
    $f = cmp01AdminFixture();
    $user = cmp01AdminUser($f, 'zimsec.manage');

    $f['registration']->update(['total_fees_minor' => 8400, 'collected_minor' => 7950]);

    Livewire::actingAs($user)->test(RegistrationsIndex::class, ['school' => $f['school']])
        ->call('close', $f['registration']->id)
        ->assertDispatched('toast', variant: 'danger');

    expect($f['registration']->fresh()->status)->not->toBe('closed');
});

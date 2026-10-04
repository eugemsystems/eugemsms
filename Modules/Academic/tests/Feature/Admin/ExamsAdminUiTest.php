<?php

use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Modules\Academic\Domain\Actions\CreateExaminationPaperAction;
use Modules\Academic\Domain\Actions\CreateExaminationSessionAction;
use Modules\Academic\Domain\DataObjects\CreateExaminationPaperData;
use Modules\Academic\Domain\DataObjects\CreateExaminationSessionData;
use Modules\Academic\Livewire\Exams\Malpractice;
use Modules\Academic\Livewire\Exams\PaperVault;
use Modules\Academic\Livewire\Exams\Scripts;
use Modules\Academic\Models\ExaminationPaper;
use Modules\Academic\Models\ExaminationSession;
use Modules\Academic\Models\ScriptBatch;
use Modules\Academic\Models\Subject;
use Modules\Academic\Models\Venue;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolSection;
use Modules\Core\Models\Term;
use Modules\People\Models\Staff;

/**
 * Book E ACA-07 admin UI — Examinations Administration. Own,
 * distinctly-named fixture — see `StaffAdminUiTest`'s own note on why a
 * Pest helper defined in one test file can't be relied on from another
 * run standalone.
 *
 * @return array{school: School, year: AcademicYear, term: Term, gradeLevel: GradeLevel, session: ExaminationSession, user: User}
 */
function examsAdminFixture(): array
{
    $school = School::factory()->create();
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->current()->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->current()->create();
    $section = SchoolSection::factory()->for($school)->create();
    $gradeLevel = GradeLevel::factory()->for($school)->for($section, 'section')->create();
    $user = User::factory()->create();

    $session = app(CreateExaminationSessionAction::class)->execute(new CreateExaminationSessionData(
        schoolId: $school->id,
        academicYearId: $year->id,
        termId: $term->id,
        name: 'End of Term 3 Examinations',
        examType: 'end_of_term',
        examBody: 'internal',
        affectedLevels: [$gradeLevel->id],
        startsOn: Carbon::now()->addWeek(),
        endsOn: Carbon::now()->addWeeks(2),
        createdBy: $user->id,
    ));

    return ['school' => $school, 'year' => $year, 'term' => $term, 'gradeLevel' => $gradeLevel, 'session' => $session, 'user' => $user];
}

/**
 * @param  array<string, mixed>  $f
 */
function examsAdminUser(array $f, string ...$permissionNames): User
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
 * A user with its own linked `Staff` row, for actions whose
 * acting-identity parameter is a `staff.id` resolved from the
 * logged-in user (`Staff::where('user_id', Auth::id())`) — see
 * `.ai/rules/academic.md`'s note on this exact id-space bug.
 *
 * @param  array<string, mixed>  $f
 * @return array{user: User, staff: Staff}
 */
function examsAdminStaffUser(array $f, string ...$permissionNames): array
{
    $user = examsAdminUser($f, ...$permissionNames);
    $staff = Staff::factory()->create(['school_id' => $f['school']->id, 'user_id' => $user->id]);

    return ['user' => $user, 'staff' => $staff];
}

/**
 * @param  array<string, mixed>  $f
 */
function examsAdminPaper(array $f, ?int $setterStaffId = null): ExaminationPaper
{
    $subject = Subject::factory()->for($f['school'])->create();

    return app(CreateExaminationPaperAction::class)->execute(new CreateExaminationPaperData(
        schoolId: $f['school']->id,
        sessionId: $f['session']->id,
        subjectId: $subject->id,
        gradeLevelId: $f['gradeLevel']->id,
        paperNumber: '1',
        paperName: 'Paper 1: Theory',
        componentType: 'theory',
        maxMark: 100,
        weightPercent: 100,
        durationMinutes: 120,
        setterStaffId: $setterStaffId,
    ));
}

it('serves every Exams screen with no route parameter through a real routed request', function (): void {
    $f = examsAdminFixture();
    $user = examsAdminUser(
        $f,
        'academic.exams.manage', 'academic.exams.paper_manage', 'academic.exams.script_manage',
        'academic.exams.mark', 'academic.exams.moderate', 'academic.exams.arrangements_manage',
        'academic.exams.results_process',
    );

    $this->actingAs($user)->get(route('academic.exams.sessions', $f['school']))->assertOk();
    $this->actingAs($user)->get(route('academic.exams.papers', $f['school']))->assertOk();
    $this->actingAs($user)->get(route('academic.exams.paper-vault', $f['school']))->assertOk();
    $this->actingAs($user)->get(route('academic.exams.candidates', $f['school']))->assertOk();
    $this->actingAs($user)->get(route('academic.exams.seating', $f['school']))->assertOk();
    $this->actingAs($user)->get(route('academic.exams.invigilation', $f['school']))->assertOk();
    $this->actingAs($user)->get(route('academic.exams.scripts', $f['school']))->assertOk();
    $this->actingAs($user)->get(route('academic.exams.mark-entry', $f['school']))->assertOk();
    $this->actingAs($user)->get(route('academic.exams.variance', $f['school']))->assertOk();
    $this->actingAs($user)->get(route('academic.exams.moderate', $f['school']))->assertOk();
    $this->actingAs($user)->get(route('academic.exams.arrangements', $f['school']))->assertOk();
    $this->actingAs($user)->get(route('academic.exams.results', $f['school']))->assertOk();
});

it('refuses Malpractice to a user holding only the general academic.exams.manage permission (BR-ACA-07-019)', function (): void {
    $f = examsAdminFixture();
    $user = examsAdminUser($f, 'academic.exams.manage');

    Livewire::actingAs($user)->test(Malpractice::class, ['school' => $f['school']])->assertForbidden();
});

it('serves Malpractice to a user holding the dedicated malpractice_view permission', function (): void {
    $f = examsAdminFixture();
    $user = examsAdminUser($f, 'academic.exams.malpractice_view');

    $this->actingAs($user)->get(route('academic.exams.malpractice', $f['school']))->assertOk();
});

it('refuses a paper download before its release_at, with no override for any role (AC-ACA-07-001)', function (): void {
    $f = examsAdminFixture();
    ['staff' => $setter] = examsAdminStaffUser($f, 'academic.exams.paper_manage');
    ['staff' => $vetter, 'user' => $vetterUser] = examsAdminStaffUser($f, 'academic.exams.paper_manage');
    $paper = examsAdminPaper($f, $setter->id);

    Livewire::actingAs($vetterUser)->test(PaperVault::class, ['school' => $f['school']])
        ->call('vet', $paper->id);

    expect(ExaminationPaper::find($paper->id)->status)->toBe('vetted');

    Livewire::actingAs($vetterUser)->test(PaperVault::class, ['school' => $f['school']])
        ->set("releaseAt.{$paper->id}", now()->addDay()->format('Y-m-d\TH:i'))
        ->call('seal', $paper->id);

    $sealed = ExaminationPaper::find($paper->id);
    expect($sealed->status)->toBe('sealed')
        ->and($sealed->release_at->isFuture())->toBeTrue();

    Livewire::actingAs($vetterUser)->test(PaperVault::class, ['school' => $f['school']])
        ->call('release', $paper->id);

    // Refused — release_at is tomorrow. No override exists for any role.
    expect(ExaminationPaper::find($paper->id)->status)->toBe('sealed')
        ->and(ExaminationPaper::find($paper->id)->released_at)->toBeNull();
});

it('refuses a paper\'s setter from vetting their own paper (AC-ACA-07-003)', function (): void {
    $f = examsAdminFixture();
    ['staff' => $setter, 'user' => $setterUser] = examsAdminStaffUser($f, 'academic.exams.paper_manage');
    $paper = examsAdminPaper($f, $setter->id);

    Livewire::actingAs($setterUser)->test(PaperVault::class, ['school' => $f['school']])
        ->call('vet', $paper->id);

    expect(ExaminationPaper::find($paper->id)->status)->not->toBe('vetted');
});

it('sets a script batch to discrepancy immediately on a count mismatch, never silently resolved (AC-ACA-07-004)', function (): void {
    $f = examsAdminFixture();
    ['staff' => $collector, 'user' => $user] = examsAdminStaffUser($f, 'academic.exams.script_manage');
    $paper = examsAdminPaper($f);
    $venue = Venue::factory()->for($f['school'])->create();

    Livewire::actingAs($user)->test(Scripts::class, ['school' => $f['school']])
        ->set('paperId', $paper->id)
        ->set('collectVenueId', $venue->id)
        ->set('scriptCount', '41')
        ->set('expectedCount', '42')
        ->set('collectedByStaffId', $collector->id)
        ->call('collect');

    $batch = ScriptBatch::where('paper_id', $paper->id)->first();

    expect($batch->status)->toBe('discrepancy')
        ->and($batch->custodyLog()->first()->discrepancy_note)->not->toBeNull();
});

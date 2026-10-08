<?php

use App\Models\User;
use Livewire\Livewire;
use Modules\Academic\Domain\Actions\AnalyseExaminationSessionAction;
use Modules\Academic\Domain\Actions\CreateExaminationPaperAction;
use Modules\Academic\Domain\Actions\CreateExaminationSessionAction;
use Modules\Academic\Domain\Actions\CreateGradingScaleAction;
use Modules\Academic\Domain\Actions\ProcessExaminationResultsAction;
use Modules\Academic\Domain\DataObjects\AnalyseExaminationSessionData;
use Modules\Academic\Domain\DataObjects\CreateExaminationPaperData;
use Modules\Academic\Domain\DataObjects\CreateExaminationSessionData;
use Modules\Academic\Domain\DataObjects\CreateGradingScaleData;
use Modules\Academic\Domain\DataObjects\GradeBandInput;
use Modules\Academic\Domain\DataObjects\ProcessExaminationResultsData;
use Modules\Academic\Livewire\Exams\Analysis;
use Modules\Academic\Models\ExaminationCandidate;
use Modules\Academic\Models\ExaminationMark;
use Modules\Academic\Models\ExaminationPaper;
use Modules\Academic\Models\LearnerSubjectEnrolment;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\Actions\Documents\CreateNumberingSeriesAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\DataObjects\Documents\CreateNumberingSeriesData;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Term;

/**
 * Book E ACA-07 §5 — `AnalyseExaminationSessionAction`/`Exams\Analysis`
 * (`Exams\Results`'s own docblock used to say "no Action computes it").
 * Reuses `aca07Fixture()`/`aca07Student()`/`aca07Paper()` from
 * `Aca07ExaminationsTest`, so run the module directory.
 *
 * @param  array<string, mixed>  $f
 */
function examsUser(array $f, string ...$permissionNames): User
{
    $user = User::factory()->create();
    $user->schools()->attach($f['school'], ['status' => 'active']);

    $grants = array_map(function (string $name): PermissionGrantData {
        $parts = explode('.', $name);
        $permission = Permission::firstOrCreate(['name' => $name], ['guard_name' => 'web', 'module_code' => strtoupper($parts[0]), 'resource' => $parts[1] ?? $parts[0], 'action' => end($parts)]);

        return new PermissionGrantData($permission->id, PermissionScope::School);
    }, $permissionNames);

    app(UpdateUserPermissionsAction::class)->execute(new UserPermissionData(userId: $user->id, schoolId: $f['school']->id, grants: $grants));

    return $user;
}

/**
 * @param  array<string, mixed>  $f
 */
function settledCandidateMark(array $f, ExaminationPaper $paper, string $firstName, string $rawMark): void
{
    $student = aca07Student($f, $firstName);
    LearnerSubjectEnrolment::factory()->create([
        'school_id' => $f['school']->id, 'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id,
        'student_id' => $student->id, 'subject_id' => $f['subject']->id, 'status' => 'active', 'added_by' => $f['user']->id,
    ]);
    $candidate = ExaminationCandidate::factory()->create([
        'school_id' => $f['school']->id, 'session_id' => $paper->session_id, 'student_id' => $student->id,
    ]);
    ExaminationMark::factory()->create([
        'school_id' => $f['school']->id, 'paper_id' => $paper->id, 'candidate_id' => $candidate->id,
        'student_id' => $student->id, 'status' => 'final', 'raw_mark' => $rawMark, 'percent' => $rawMark,
    ]);
}

it('reports an ungraded distribution and a null pass rate when the subject has no grading scale', function (): void {
    $f = aca07Fixture();
    $paper = aca07Paper($f, weightPercent: 100.0);
    settledCandidateMark($f, $paper, 'Tadiwa', '78.00');

    $report = app(AnalyseExaminationSessionAction::class)->execute(new AnalyseExaminationSessionData(sessionId: $paper->session_id));

    expect($report['subject_comparison'][0]['average_percent'])->toBe(78.0)
        ->and($report['subject_comparison'][0]['pass_rate_percent'])->toBeNull()
        ->and($report['distributions'][0]['bands'])->toBe(['Ungraded' => 1]);
});

it('computes grade distribution and pass rate once the subject has a grading scale', function (): void {
    $f = aca07Fixture();
    $paper = aca07Paper($f, weightPercent: 100.0);

    $scale = app(CreateGradingScaleAction::class)->execute(new CreateGradingScaleData(
        schoolId: $f['school']->id, code: 'PF', name: 'Pass/Fail', scaleType: 'percentage', passGrade: 'PASS',
        bands: [
            new GradeBandInput(grade: 'FAIL', minPercent: 0, maxPercent: 50, isPass: false),
            new GradeBandInput(grade: 'PASS', minPercent: 50, maxPercent: 100, isPass: true),
        ],
    ));
    $f['subject']->update(['grading_scale_id' => $scale->id]);

    settledCandidateMark($f, $paper, 'Rutendo', '78.00');
    settledCandidateMark($f, $paper, 'Chenai', '30.00');

    $report = app(AnalyseExaminationSessionAction::class)->execute(new AnalyseExaminationSessionData(sessionId: $paper->session_id));

    expect($report['distributions'][0]['bands'])->toBe(['PASS' => 1, 'FAIL' => 1])
        ->and($report['subject_comparison'][0]['pass_rate_percent'])->toBe(50.0);
});

it('computes a year-on-year trend across sessions of the same exam type and subject', function (): void {
    $f = aca07Fixture();
    $paperYear1 = aca07Paper($f, weightPercent: 100.0);
    settledCandidateMark($f, $paperYear1, 'Year1Student', '60.00');

    $year2 = AcademicYear::factory()->for($f['school'])->create();
    $term2 = Term::factory()->for($f['school'])->for($year2, 'academicYear')->create();
    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
        schoolId: $f['school']->id, documentType: 'admission', pattern: '{SCHOOL}/{YEAR}/{SEQ:4}', academicYearId: $year2->id,
    ));

    $session2 = app(CreateExaminationSessionAction::class)->execute(new CreateExaminationSessionData(
        schoolId: $f['school']->id, academicYearId: $year2->id, termId: $term2->id,
        name: 'End of Term Exams Y2', examType: 'end_of_term', examBody: 'internal',
        affectedLevels: [$f['gradeLevel']->id], startsOn: now()->addYear(), endsOn: now()->addYear()->addWeek(),
        createdBy: $f['user']->id,
    ));
    $paperYear2 = app(CreateExaminationPaperAction::class)->execute(new CreateExaminationPaperData(
        schoolId: $f['school']->id, sessionId: $session2->id, subjectId: $f['subject']->id,
        gradeLevelId: $f['gradeLevel']->id, paperNumber: '1', paperName: 'Paper 1', componentType: 'theory',
        maxMark: 100.0, weightPercent: 100.0, durationMinutes: 120, setterStaffId: $f['setter']->id,
    ));

    $yearFixture = $f;
    $yearFixture['year'] = $year2;
    $yearFixture['term'] = $term2;
    settledCandidateMark($yearFixture, $paperYear2, 'Year2Student', '80.00');

    $report = app(AnalyseExaminationSessionAction::class)->execute(new AnalyseExaminationSessionData(sessionId: $paperYear2->session_id));

    $trend = collect($report['year_on_year'][0]['years'])->keyBy('academic_year_id');

    expect($trend->get($f['year']->id)['average_percent'])->toBe(60.0)
        ->and($trend->get($year2->id)['average_percent'])->toBe(80.0);
});

it('renders the Exams\\Analysis screen for a selected session, requiring academic.exams.view', function (): void {
    $f = aca07Fixture();
    $paper = aca07Paper($f, weightPercent: 100.0);
    $paper->session->update(['status' => 'marking']);
    settledCandidateMark($f, $paper, 'Tanaka', '78.00');

    app(ProcessExaminationResultsAction::class)->execute(new ProcessExaminationResultsData(
        sessionId: $paper->session_id, processedByUserId: $f['user']->id,
    ));

    $user = examsUser($f, 'academic.exams.view');

    Livewire::actingAs($user)->test(Analysis::class, ['school' => $f['school']])
        ->set('sessionId', $paper->session_id)
        ->assertSee('Subject comparison');

    Livewire::actingAs(examsUser($f))->test(Analysis::class, ['school' => $f['school']])
        ->assertForbidden();
});

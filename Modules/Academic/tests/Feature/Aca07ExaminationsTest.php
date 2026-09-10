<?php

use App\Models\User;
use Modules\Academic\Domain\Actions\CreateExaminationPaperAction;
use Modules\Academic\Domain\Actions\CreateExaminationSessionAction;
use Modules\Academic\Domain\Actions\DecideMalpracticeOutcomeAction;
use Modules\Academic\Domain\Actions\EnterExamMarkAction;
use Modules\Academic\Domain\Actions\EnterThirdExamMarkAction;
use Modules\Academic\Domain\Actions\HandoverScriptBatchAction;
use Modules\Academic\Domain\Actions\ProcessExaminationResultsAction;
use Modules\Academic\Domain\Actions\PublishExaminationResultsAction;
use Modules\Academic\Domain\Actions\ReleaseExaminationPaperAction;
use Modules\Academic\Domain\Actions\ReportMalpracticeIncidentAction;
use Modules\Academic\Domain\Actions\SealExaminationPaperAction;
use Modules\Academic\Domain\Actions\VetExaminationPaperAction;
use Modules\Academic\Domain\DataObjects\CreateExaminationPaperData;
use Modules\Academic\Domain\DataObjects\CreateExaminationSessionData;
use Modules\Academic\Domain\DataObjects\DecideMalpracticeOutcomeData;
use Modules\Academic\Domain\DataObjects\EnterExamMarkData;
use Modules\Academic\Domain\DataObjects\EnterThirdExamMarkData;
use Modules\Academic\Domain\DataObjects\HandoverScriptBatchData;
use Modules\Academic\Domain\DataObjects\ProcessExaminationResultsData;
use Modules\Academic\Domain\DataObjects\PublishExaminationResultsData;
use Modules\Academic\Domain\DataObjects\ReleaseExaminationPaperData;
use Modules\Academic\Domain\DataObjects\ReportMalpracticeIncidentData;
use Modules\Academic\Domain\DataObjects\SealExaminationPaperData;
use Modules\Academic\Domain\DataObjects\VetExaminationPaperData;
use Modules\Academic\Domain\Exceptions\PaperComponentWeightMismatchException;
use Modules\Academic\Domain\Exceptions\PaperReleaseNotYetDueException;
use Modules\Academic\Domain\Exceptions\SetterCannotVetOwnPaperException;
use Modules\Academic\Models\AssessmentMark;
use Modules\Academic\Models\CurriculumFramework;
use Modules\Academic\Models\ExaminationCandidate;
use Modules\Academic\Models\ExaminationMark;
use Modules\Academic\Models\ExaminationPaper;
use Modules\Academic\Models\LearnerSubjectEnrolment;
use Modules\Academic\Models\ScriptBatch;
use Modules\Academic\Models\Subject;
use Modules\Core\Domain\Actions\Documents\CreateNumberingSeriesAction;
use Modules\Core\Domain\DataObjects\Documents\CreateNumberingSeriesData;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Domain\Support\Settings\SettingScope;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\DataAccessLogEntry;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolSection;
use Modules\Core\Models\SettingValue;
use Modules\Core\Models\Term;
use Modules\People\Domain\Actions\CreateStudentAction;
use Modules\People\Domain\DataObjects\CreateStudentData;
use Modules\People\Models\Staff;
use Modules\People\Models\Student;

/**
 * @return array{school: School, year: AcademicYear, term: Term, gradeLevel: GradeLevel, framework: CurriculumFramework, subject: Subject, user: User, setter: Staff, vetter: Staff}
 */
function aca07Fixture(): array
{
    $school = School::factory()->create();
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create(['is_current' => true]);
    $section = SchoolSection::factory()->for($school)->create();
    $gradeLevel = GradeLevel::factory()->for($school)->for($section, 'section')->create();
    $framework = CurriculumFramework::factory()->for($school)->create();
    $subject = Subject::factory()->for($school)->create(['framework_id' => $framework->id]);
    $user = User::factory()->create();
    $setter = Staff::factory()->for($school)->create();
    $vetter = Staff::factory()->for($school)->create();

    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
        schoolId: $school->id, documentType: 'admission', pattern: '{SCHOOL}/{YEAR}/{SEQ:4}', academicYearId: $year->id,
    ));

    return compact('school', 'year', 'term', 'gradeLevel', 'framework', 'subject', 'user', 'setter', 'vetter');
}

/**
 * @param  array<string, mixed>  $f
 */
function aca07Student(array $f, string $firstName): Student
{
    return app(CreateStudentAction::class)->execute(new CreateStudentData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        firstName: $firstName, lastName: 'Chikafu', dateOfBirth: now()->subYears(17), gender: 'male',
        enrolmentType: 'FULL_TIME', residency: 'DAY', sectionId: $f['gradeLevel']->section_id,
        gradeLevelId: $f['gradeLevel']->id, entryCohortYear: (int) now()->year,
        createdByUserId: $f['user']->id, skipDuplicateCheck: true,
    ));
}

/**
 * @param  array<string, mixed>  $f
 */
function aca07Paper(array $f, float $weightPercent = 100.0): ExaminationPaper
{
    $session = app(CreateExaminationSessionAction::class)->execute(new CreateExaminationSessionData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        name: 'End of Term Exams', examType: 'end_of_term', examBody: 'internal',
        affectedLevels: [$f['gradeLevel']->id], startsOn: now(), endsOn: now()->addWeek(),
        createdBy: $f['user']->id,
    ));

    return app(CreateExaminationPaperAction::class)->execute(new CreateExaminationPaperData(
        schoolId: $f['school']->id, sessionId: $session->id, subjectId: $f['subject']->id,
        gradeLevelId: $f['gradeLevel']->id, paperNumber: '1', paperName: 'Paper 1', componentType: 'theory',
        maxMark: 100.0, weightPercent: $weightPercent, durationMinutes: 120, setterStaffId: $f['setter']->id,
    ));
}

it('refuses release before release_at with no override path', function (): void {
    $f = aca07Fixture();
    $paper = aca07Paper($f);

    app(VetExaminationPaperAction::class)->execute(new VetExaminationPaperData(paperId: $paper->id, vettedByStaffId: $f['vetter']->id));
    app(SealExaminationPaperAction::class)->execute(new SealExaminationPaperData(paperId: $paper->id, releaseAt: now()->addDay()));

    expect(fn () => app(ReleaseExaminationPaperAction::class)->execute(new ReleaseExaminationPaperData(
        paperId: $paper->id, requestedByUserId: $f['user']->id,
    )))->toThrow(PaperReleaseNotYetDueException::class);

    $paper->refresh();
    expect($paper->status)->toBe('sealed')->and($paper->released_at)->toBeNull();
});

it('releases a paper once release_at has passed and logs the access', function (): void {
    $f = aca07Fixture();
    $paper = aca07Paper($f);

    app(VetExaminationPaperAction::class)->execute(new VetExaminationPaperData(paperId: $paper->id, vettedByStaffId: $f['vetter']->id));
    app(SealExaminationPaperAction::class)->execute(new SealExaminationPaperData(paperId: $paper->id, releaseAt: now()->subMinute()));

    $released = app(ReleaseExaminationPaperAction::class)->execute(new ReleaseExaminationPaperData(
        paperId: $paper->id, requestedByUserId: $f['user']->id,
    ));

    expect($released->status)->toBe('released')
        ->and($released->released_at)->not->toBeNull()
        ->and(DataAccessLogEntry::where('resource_type', 'examination_paper')->where('resource_id', $paper->id)->exists())->toBeTrue();
});

it('refuses a paper setter from vetting their own paper', function (): void {
    $f = aca07Fixture();
    $paper = aca07Paper($f);

    expect(fn () => app(VetExaminationPaperAction::class)->execute(new VetExaminationPaperData(
        paperId: $paper->id, vettedByStaffId: $f['setter']->id,
    )))->toThrow(SetterCannotVetOwnPaperException::class);
});

it('sets a script batch to discrepancy and logs both counts on a handover mismatch', function (): void {
    $f = aca07Fixture();
    $paper = aca07Paper($f);

    $batch = ScriptBatch::factory()->create([
        'school_id' => $f['school']->id, 'paper_id' => $paper->id,
        'script_count' => 42, 'expected_count' => 42, 'status' => 'collected',
        'current_holder_staff_id' => $f['setter']->id,
    ]);

    $handedOver = app(HandoverScriptBatchAction::class)->execute(new HandoverScriptBatchData(
        batchId: $batch->id, toStaffId: $f['vetter']->id, scriptCount: 41, action: 'received',
        recordedByUserId: $f['user']->id,
    ));

    expect($handedOver->status)->toBe('discrepancy');

    $entry = $batch->custodyLog()->latest('id')->first();
    expect($entry->script_count)->toBe(41)
        ->and($entry->discrepancy_note)->toContain('42')
        ->and($entry->discrepancy_note)->toContain('41');
});

it('routes a variance beyond threshold to third marking and settles from it', function (): void {
    $f = aca07Fixture();
    $paper = aca07Paper($f);

    $student = aca07Student($f, 'Tanaka');
    $candidate = ExaminationCandidate::factory()->create([
        'school_id' => $f['school']->id, 'session_id' => $paper->session_id, 'student_id' => $student->id,
    ]);

    $firstMarker = Staff::factory()->for($f['school'])->create();
    $secondMarker = Staff::factory()->for($f['school'])->create();
    $thirdMarker = Staff::factory()->for($f['school'])->create();

    ExaminationMark::factory()->create([
        'school_id' => $f['school']->id, 'paper_id' => $paper->id, 'candidate_id' => $candidate->id,
        'student_id' => $student->id, 'status' => 'pending',
    ]);

    SettingValue::create(['setting_key' => 'exams.double_marking_enabled', 'scope_type' => SettingScope::School, 'scope_id' => $f['school']->id, 'value' => '1']);
    SettingValue::create(['setting_key' => 'exams.double_marking_variance_threshold', 'scope_type' => SettingScope::School, 'scope_id' => $f['school']->id, 'value' => '5']);

    app(EnterExamMarkAction::class)->execute(new EnterExamMarkData(
        paperId: $paper->id, candidateId: $candidate->id, markerStaffId: $firstMarker->id, mark: 62.0,
    ));

    $afterSecond = app(EnterExamMarkAction::class)->execute(new EnterExamMarkData(
        paperId: $paper->id, candidateId: $candidate->id, markerStaffId: $secondMarker->id, mark: 71.0,
    ));

    expect($afterSecond->status)->toBe('variance_review')
        ->and($afterSecond->raw_mark)->toBeNull();

    $settled = app(EnterThirdExamMarkAction::class)->execute(new EnterThirdExamMarkData(
        paperId: $paper->id, candidateId: $candidate->id, markerStaffId: $thirdMarker->id, mark: 68.0,
    ));

    expect($settled->status)->toBe('final')
        ->and((float) $settled->raw_mark)->toBe(68.0);
});

it('voids a candidate mark on a paper_annulled malpractice outcome while keeping it visible', function (): void {
    $f = aca07Fixture();
    $paper = aca07Paper($f);
    $student = aca07Student($f, 'Rudo');
    $candidate = ExaminationCandidate::factory()->create([
        'school_id' => $f['school']->id, 'session_id' => $paper->session_id, 'student_id' => $student->id,
    ]);
    $mark = ExaminationMark::factory()->create([
        'school_id' => $f['school']->id, 'paper_id' => $paper->id, 'candidate_id' => $candidate->id,
        'student_id' => $student->id, 'status' => 'final', 'raw_mark' => '55.00', 'percent' => '55.00',
    ]);

    $incident = app(ReportMalpracticeIncidentAction::class)->execute(new ReportMalpracticeIncidentData(
        schoolId: $f['school']->id, sessionId: $paper->session_id, incidentType: 'unauthorised_material',
        description: 'Notes found in pocket.', reportedByUserId: $f['user']->id, occurredAt: now(),
        paperId: $paper->id, candidateId: $candidate->id,
    ));

    app(DecideMalpracticeOutcomeAction::class)->execute(new DecideMalpracticeOutcomeData(
        incidentId: $incident->id, outcome: 'paper_annulled', outcomeByUserId: $f['user']->id,
        investigationNotes: 'Confirmed by two invigilators.',
    ));

    $mark->refresh();
    expect($mark->status)->toBe('void')
        ->and((float) $mark->raw_mark)->toBe(55.0)
        ->and(ExaminationMark::find($mark->id))->not->toBeNull();
});

it('blocks results processing when a subject/level paper weight total falls short of 100%', function (): void {
    $f = aca07Fixture();
    $paper = aca07Paper($f, weightPercent: 90.0);
    $paper->session->update(['status' => 'marking']);

    expect(fn () => app(ProcessExaminationResultsAction::class)->execute(new ProcessExaminationResultsData(
        sessionId: $paper->session_id, processedByUserId: $f['user']->id,
    )))->toThrow(PaperComponentWeightMismatchException::class);
});

it('processes results into ACA-05 and stages publication behind an explicit step', function (): void {
    $f = aca07Fixture();
    $paper = aca07Paper($f, weightPercent: 100.0);
    $paper->session->update(['status' => 'marking']);

    $student = aca07Student($f, 'Simba');
    LearnerSubjectEnrolment::factory()->create([
        'school_id' => $f['school']->id, 'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id,
        'student_id' => $student->id, 'subject_id' => $f['subject']->id, 'status' => 'active', 'added_by' => $f['user']->id,
    ]);
    $candidate = ExaminationCandidate::factory()->create([
        'school_id' => $f['school']->id, 'session_id' => $paper->session_id, 'student_id' => $student->id,
    ]);
    ExaminationMark::factory()->create([
        'school_id' => $f['school']->id, 'paper_id' => $paper->id, 'candidate_id' => $candidate->id,
        'student_id' => $student->id, 'status' => 'final', 'raw_mark' => '78.00', 'percent' => '78.00',
    ]);

    $session = app(ProcessExaminationResultsAction::class)->execute(new ProcessExaminationResultsData(
        sessionId: $paper->session_id, processedByUserId: $f['user']->id,
    ));

    expect($session->status)->toBe('results_ready');
    expect(AssessmentMark::where('student_id', $student->id)->where('raw_mark', '78.00')->exists())->toBeTrue();

    $published = app(PublishExaminationResultsAction::class)->execute(new PublishExaminationResultsData(
        sessionId: $paper->session_id, publishedByUserId: $f['user']->id,
    ));

    expect($published->status)->toBe('published');
});

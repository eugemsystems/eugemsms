<?php

use App\Models\User;
use Modules\Academic\Domain\Actions\CloseCbtTestAction;
use Modules\Academic\Domain\Actions\ComputeItemAnalysisAction;
use Modules\Academic\Domain\Actions\CreateCbtTestAction;
use Modules\Academic\Domain\Actions\CreateQuestionBankItemAction;
use Modules\Academic\Domain\Actions\MarkCbtResponseAction;
use Modules\Academic\Domain\Actions\PublishCbtResultsAction;
use Modules\Academic\Domain\Actions\RecordFocusEventAction;
use Modules\Academic\Domain\Actions\SaveResponseAction;
use Modules\Academic\Domain\Actions\ScheduleCbtTestAction;
use Modules\Academic\Domain\Actions\StartAttemptAction;
use Modules\Academic\Domain\Actions\SubmitAttemptAction;
use Modules\Academic\Domain\DataObjects\CloseCbtTestData;
use Modules\Academic\Domain\DataObjects\ComputeItemAnalysisData;
use Modules\Academic\Domain\DataObjects\CreateCbtTestData;
use Modules\Academic\Domain\DataObjects\CreateQuestionBankItemData;
use Modules\Academic\Domain\DataObjects\MarkCbtResponseData;
use Modules\Academic\Domain\DataObjects\PublishCbtResultsData;
use Modules\Academic\Domain\DataObjects\RecordFocusEventData;
use Modules\Academic\Domain\DataObjects\SaveResponseData;
use Modules\Academic\Domain\DataObjects\ScheduleCbtTestData;
use Modules\Academic\Domain\DataObjects\StartAttemptData;
use Modules\Academic\Domain\DataObjects\SubmitAttemptData;
use Modules\Academic\Domain\Exceptions\InsufficientQuestionBankException;
use Modules\Academic\Domain\Exceptions\ResultsNotReadyException;
use Modules\Academic\Models\Assessment;
use Modules\Academic\Models\AssessmentMark;
use Modules\Academic\Models\AssessmentType;
use Modules\Academic\Models\CurriculumFramework;
use Modules\Academic\Models\ExaminationSession;
use Modules\Academic\Models\QuestionBankItem;
use Modules\Academic\Models\SpecialArrangement;
use Modules\Academic\Models\Subject;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\People\Models\Student;

/**
 * @return array{school: School, year: AcademicYear, term: Term, subject: Subject, user: User}
 */
function aca09Fixture(): array
{
    $school = School::factory()->create();
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create(['is_current' => true]);
    $framework = CurriculumFramework::factory()->for($school)->create();
    $subject = Subject::factory()->for($school)->create(['framework_id' => $framework->id]);
    $user = User::factory()->create();

    return ['school' => $school, 'year' => $year, 'term' => $term, 'subject' => $subject, 'user' => $user];
}

/**
 * @param  array<string, mixed>  $f
 */
function aca09McqItem(array $f, int $correctOption = 0): QuestionBankItem
{
    return app(CreateQuestionBankItemAction::class)->execute(new CreateQuestionBankItemData(
        schoolId: $f['school']->id, subjectId: $f['subject']->id, itemType: 'mcq', difficulty: 'medium',
        prompt: 'What is the SI unit of force?', maxMark: 2, isAutoMarkable: true, createdByUserId: $f['user']->id,
        options: ['Newton', 'Joule', 'Watt'], correctAnswer: [$correctOption],
    ));
}

it('assembles a rule-based test from the question bank and refuses when the bank is short (BR-ACA-09-005)', function (): void {
    $f = aca09Fixture();
    aca09McqItem($f);
    aca09McqItem($f);

    app(CreateCbtTestAction::class)->execute(new CreateCbtTestData(
        schoolId: $f['school']->id, termId: $f['term']->id, title: 'Insufficient bank test',
        subjectId: $f['subject']->id, durationMinutes: 30, opensAt: now()->subHour(), closesAt: now()->addDay(),
        createdByUserId: $f['user']->id, assemblyMethod: 'rule_based',
        assemblyRules: ['count' => 10, 'mix' => ['medium' => 1.0]],
    ));
})->throws(InsufficientQuestionBankException::class);

it('assembles a rule-based test successfully once the bank has enough items', function (): void {
    $f = aca09Fixture();
    for ($i = 0; $i < 5; $i++) {
        aca09McqItem($f);
    }

    $test = app(CreateCbtTestAction::class)->execute(new CreateCbtTestData(
        schoolId: $f['school']->id, termId: $f['term']->id, title: 'Rule-based test',
        subjectId: $f['subject']->id, durationMinutes: 30, opensAt: now()->subHour(), closesAt: now()->addDay(),
        createdByUserId: $f['user']->id, assemblyMethod: 'rule_based',
        assemblyRules: ['count' => 5, 'mix' => ['medium' => 1.0]],
    ));

    expect($test->question_ids)->toHaveCount(5);
});

it('resumes an interrupted attempt with every saved response intact and the identical seeded order, remaining time reflecting only elapsed time (AC-ACA-09-001/002/004)', function (): void {
    $f = aca09Fixture();
    $questions = [aca09McqItem($f), aca09McqItem($f), aca09McqItem($f), aca09McqItem($f)];
    $student = Student::factory()->for($f['school'])->create();

    $test = app(CreateCbtTestAction::class)->execute(new CreateCbtTestData(
        schoolId: $f['school']->id, termId: $f['term']->id, title: 'Resumable test', subjectId: $f['subject']->id,
        durationMinutes: 20, opensAt: now()->subHour(), closesAt: now()->addDay(), createdByUserId: $f['user']->id,
        assemblyMethod: 'manual', questionIds: array_map(fn ($q) => $q->id, $questions),
    ));
    app(ScheduleCbtTestAction::class)->execute(new ScheduleCbtTestData(testId: $test->id));

    $attempt = app(StartAttemptAction::class)->execute(new StartAttemptData(testId: $test->id, studentId: $student->id));
    app(SaveResponseAction::class)->execute(new SaveResponseData(attemptId: $attempt->id, questionId: $questions[0]->id, responseValue: [0]));
    app(SaveResponseAction::class)->execute(new SaveResponseData(attemptId: $attempt->id, questionId: $questions[1]->id, responseValue: [0]));

    $resumed = app(StartAttemptAction::class)->execute(new StartAttemptData(testId: $test->id, studentId: $student->id));

    expect($resumed->id)->toBe($attempt->id)
        ->and($resumed->seeded_question_order)->toBe($attempt->seeded_question_order)
        ->and($resumed->responses()->count())->toBe(2)
        ->and($resumed->remainingSeconds())->toBeGreaterThan(0)
        ->and($resumed->remainingSeconds())->toBeLessThanOrEqual(20 * 60);
});

it('extends a candidate\'s duration by their approved ACA-07 extra-time arrangement without separate configuration (AC-ACA-09-003)', function (): void {
    $f = aca09Fixture();
    $question = aca09McqItem($f);
    $student = Student::factory()->for($f['school'])->create();
    $session = ExaminationSession::factory()->for($f['school'])->create(['created_by' => $f['user']->id]);
    SpecialArrangement::factory()->create([
        'school_id' => $f['school']->id, 'session_id' => $session->id, 'student_id' => $student->id,
        'arrangement_type' => 'extra_time', 'extra_time_percent' => 25, 'status' => 'approved',
        'approved_by' => $f['user']->id, 'approved_at' => now(),
    ]);

    $test = app(CreateCbtTestAction::class)->execute(new CreateCbtTestData(
        schoolId: $f['school']->id, termId: $f['term']->id, title: 'Extra time test', subjectId: $f['subject']->id,
        durationMinutes: 40, opensAt: now()->subHour(), closesAt: now()->addDay(), createdByUserId: $f['user']->id,
        assemblyMethod: 'manual', questionIds: [$question->id],
    ));
    app(ScheduleCbtTestAction::class)->execute(new ScheduleCbtTestData(testId: $test->id));

    $attempt = app(StartAttemptAction::class)->execute(new StartAttemptData(testId: $test->id, studentId: $student->id));

    expect($attempt->extra_time_minutes)->toBe(10);
});

it('flags but does not disqualify or zero-score an attempt whose tab-switch count exceeds the limit (AC-ACA-09-005)', function (): void {
    $f = aca09Fixture();
    $question = aca09McqItem($f);
    $student = Student::factory()->for($f['school'])->create();

    $test = app(CreateCbtTestAction::class)->execute(new CreateCbtTestData(
        schoolId: $f['school']->id, termId: $f['term']->id, title: 'Monitored test', subjectId: $f['subject']->id,
        durationMinutes: 30, opensAt: now()->subHour(), closesAt: now()->addDay(), createdByUserId: $f['user']->id,
        assemblyMethod: 'manual', questionIds: [$question->id], browserFocusMonitoring: true, maxTabSwitches: 3,
    ));
    app(ScheduleCbtTestAction::class)->execute(new ScheduleCbtTestData(testId: $test->id));
    $attempt = app(StartAttemptAction::class)->execute(new StartAttemptData(testId: $test->id, studentId: $student->id));

    for ($i = 0; $i < 5; $i++) {
        $attempt = app(RecordFocusEventAction::class)->execute(new RecordFocusEventData(attemptId: $attempt->id, eventType: 'tab_switch'));
    }

    expect($attempt->tab_switch_count)->toBe(5)
        ->and($attempt->status)->toBe('flagged')
        ->and($attempt->raw_mark)->toBeNull();
});

it('auto-submits with whatever was saved when the time limit is reached, losing nothing entered before expiry (AC-ACA-09-006)', function (): void {
    $f = aca09Fixture();
    $question = aca09McqItem($f, correctOption: 0);
    $student = Student::factory()->for($f['school'])->create();

    $test = app(CreateCbtTestAction::class)->execute(new CreateCbtTestData(
        schoolId: $f['school']->id, termId: $f['term']->id, title: 'Time-limited test', subjectId: $f['subject']->id,
        durationMinutes: 10, opensAt: now()->subHour(), closesAt: now()->addDay(), createdByUserId: $f['user']->id,
        assemblyMethod: 'manual', questionIds: [$question->id],
    ));
    app(ScheduleCbtTestAction::class)->execute(new ScheduleCbtTestData(testId: $test->id));
    $attempt = app(StartAttemptAction::class)->execute(new StartAttemptData(testId: $test->id, studentId: $student->id));
    $attempt->update(['started_at' => now()->subMinutes(15)]);
    app(SaveResponseAction::class)->execute(new SaveResponseData(attemptId: $attempt->id, questionId: $question->id, responseValue: [0]));

    expect($attempt->fresh()->remainingSeconds())->toBe(0);

    $submitted = app(SubmitAttemptAction::class)->execute(new SubmitAttemptData(attemptId: $attempt->id, autoSubmitted: true));

    expect($submitted->auto_submitted)->toBeTrue()
        ->and((float) $submitted->raw_mark)->toBe(2.0)
        ->and($submitted->responses()->first()->auto_mark_correct)->toBeTrue();
});

it('auto-marks objective items instantly, queues written items for manual marking, and syncs the final gradebook mark through the ACA-05 path (BR-ACA-09-007/008/010)', function (): void {
    $f = aca09Fixture();
    $mcq = aca09McqItem($f, correctOption: 0);
    $essay = app(CreateQuestionBankItemAction::class)->execute(new CreateQuestionBankItemData(
        schoolId: $f['school']->id, subjectId: $f['subject']->id, itemType: 'essay', difficulty: 'hard',
        prompt: 'Explain Newton\'s third law.', maxMark: 8, isAutoMarkable: false, createdByUserId: $f['user']->id,
    ));
    $student = Student::factory()->for($f['school'])->create();
    $assessmentType = AssessmentType::factory()->for($f['school'])->create(['default_weight_percent' => 20]);

    $test = app(CreateCbtTestAction::class)->execute(new CreateCbtTestData(
        schoolId: $f['school']->id, termId: $f['term']->id, title: 'Mixed test', subjectId: $f['subject']->id,
        durationMinutes: 30, opensAt: now()->subHour(), closesAt: now()->addDay(), createdByUserId: $f['user']->id,
        assemblyMethod: 'manual', questionIds: [$mcq->id, $essay->id], assessmentTypeId: $assessmentType->id,
    ));

    expect($test->assessment_id)->not->toBeNull();
    $assessment = Assessment::findOrFail($test->assessment_id);
    expect((float) $assessment->max_mark)->toBe(10.0);

    app(ScheduleCbtTestAction::class)->execute(new ScheduleCbtTestData(testId: $test->id));
    $attempt = app(StartAttemptAction::class)->execute(new StartAttemptData(testId: $test->id, studentId: $student->id));
    app(SaveResponseAction::class)->execute(new SaveResponseData(attemptId: $attempt->id, questionId: $mcq->id, responseValue: [0]));
    app(SaveResponseAction::class)->execute(new SaveResponseData(attemptId: $attempt->id, questionId: $essay->id, responseValue: 'Newton\'s third law states...'));

    $submitted = app(SubmitAttemptAction::class)->execute(new SubmitAttemptData(attemptId: $attempt->id));

    expect($submitted->status)->toBe('manual_marking_pending')
        ->and((float) $submitted->raw_mark)->toBe(2.0);

    app(CloseCbtTestAction::class)->execute(new CloseCbtTestData(testId: $test->id));

    app(PublishCbtResultsAction::class)->execute(new PublishCbtResultsData(testId: $test->id, publishedByUserId: $f['user']->id));
})->throws(ResultsNotReadyException::class);

it('publishes results and syncs the final mark once every response is marked', function (): void {
    $f = aca09Fixture();
    $mcq = aca09McqItem($f, correctOption: 0);
    $essay = app(CreateQuestionBankItemAction::class)->execute(new CreateQuestionBankItemData(
        schoolId: $f['school']->id, subjectId: $f['subject']->id, itemType: 'essay', difficulty: 'hard',
        prompt: 'Explain Newton\'s third law.', maxMark: 8, isAutoMarkable: false, createdByUserId: $f['user']->id,
    ));
    $student = Student::factory()->for($f['school'])->create();
    $assessmentType = AssessmentType::factory()->for($f['school'])->create(['default_weight_percent' => 20]);

    $test = app(CreateCbtTestAction::class)->execute(new CreateCbtTestData(
        schoolId: $f['school']->id, termId: $f['term']->id, title: 'Mixed test 2', subjectId: $f['subject']->id,
        durationMinutes: 30, opensAt: now()->subHour(), closesAt: now()->addDay(), createdByUserId: $f['user']->id,
        assemblyMethod: 'manual', questionIds: [$mcq->id, $essay->id], assessmentTypeId: $assessmentType->id,
    ));
    app(ScheduleCbtTestAction::class)->execute(new ScheduleCbtTestData(testId: $test->id));

    $attempt = app(StartAttemptAction::class)->execute(new StartAttemptData(testId: $test->id, studentId: $student->id));
    app(SaveResponseAction::class)->execute(new SaveResponseData(attemptId: $attempt->id, questionId: $mcq->id, responseValue: [0]));
    $essayResponse = app(SaveResponseAction::class)->execute(new SaveResponseData(attemptId: $attempt->id, questionId: $essay->id, responseValue: 'Answer.'));

    app(SubmitAttemptAction::class)->execute(new SubmitAttemptData(attemptId: $attempt->id));

    $marked = app(MarkCbtResponseAction::class)->execute(new MarkCbtResponseData(
        responseId: $essayResponse->id, markAwarded: 6.0, markedByUserId: $f['user']->id,
    ));

    expect($marked->attempt->fresh()->status)->toBe('fully_marked')
        ->and((float) $marked->attempt->fresh()->raw_mark)->toBe(8.0);

    app(CloseCbtTestAction::class)->execute(new CloseCbtTestData(testId: $test->id));
    $published = app(PublishCbtResultsAction::class)->execute(new PublishCbtResultsData(testId: $test->id, publishedByUserId: $f['user']->id));

    expect($published->status)->toBe('results_released');

    $gradebookMark = AssessmentMark::where('assessment_id', $test->assessment_id)->where('student_id', $student->id)->first();
    expect($gradebookMark)->not->toBeNull()
        ->and((float) $gradebookMark->raw_mark)->toBe(8.0);
});

it('computes item difficulty after a test closes, from actual candidate performance (BR-ACA-09-009)', function (): void {
    $f = aca09Fixture();
    $question = aca09McqItem($f, correctOption: 0);

    $test = app(CreateCbtTestAction::class)->execute(new CreateCbtTestData(
        schoolId: $f['school']->id, termId: $f['term']->id, title: 'Analysis test', subjectId: $f['subject']->id,
        durationMinutes: 30, opensAt: now()->subHour(), closesAt: now()->addDay(), createdByUserId: $f['user']->id,
        assemblyMethod: 'manual', questionIds: [$question->id],
    ));
    app(ScheduleCbtTestAction::class)->execute(new ScheduleCbtTestData(testId: $test->id));

    foreach ([0, 0, 0, 1] as $answer) {
        $student = Student::factory()->for($f['school'])->create();
        $attempt = app(StartAttemptAction::class)->execute(new StartAttemptData(testId: $test->id, studentId: $student->id));
        app(SaveResponseAction::class)->execute(new SaveResponseData(attemptId: $attempt->id, questionId: $question->id, responseValue: [$answer]));
        app(SubmitAttemptAction::class)->execute(new SubmitAttemptData(attemptId: $attempt->id));
    }

    app(CloseCbtTestAction::class)->execute(new CloseCbtTestData(testId: $test->id));
    $results = app(ComputeItemAnalysisAction::class)->execute(new ComputeItemAnalysisData(testId: $test->id));

    expect($results)->toHaveCount(1)
        ->and($results[0]['difficultyIndex'])->toBe(75.0)
        ->and($question->fresh()->difficulty_index)->not->toBeNull();
});

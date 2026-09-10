<?php

use App\Models\User;
use Illuminate\Support\Carbon;
use Modules\Comms\Domain\Actions\AddComplaintUpdateAction;
use Modules\Comms\Domain\Actions\CheckComplaintSlaAction;
use Modules\Comms\Domain\Actions\CompleteExitInterviewAction;
use Modules\Comms\Domain\Actions\CreateSurveyAction;
use Modules\Comms\Domain\Actions\DeclineExitInterviewAction;
use Modules\Comms\Domain\Actions\GetComplaintThreadForRaiserAction;
use Modules\Comms\Domain\Actions\RaiseComplaintAction;
use Modules\Comms\Domain\Actions\RegisterMessageGatewayAction;
use Modules\Comms\Domain\Actions\RequestExitInterviewAction;
use Modules\Comms\Domain\Actions\SubmitSurveyResponseAction;
use Modules\Comms\Domain\DataObjects\RaiseComplaintData;
use Modules\Comms\Domain\DataObjects\RegisterMessageGatewayData;
use Modules\Comms\Domain\DataObjects\SubmitSurveyResponseData;
use Modules\Comms\Models\ComplaintCategory;
use Modules\Comms\Models\SurveyResponseAnswer;
use Modules\Core\Domain\Actions\Notifications\CreateNotificationTemplateAction;
use Modules\Core\Domain\DataObjects\Notifications\CreateNotificationTemplateData;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\Notification;
use Modules\Core\Models\School;
use Modules\People\Models\Staff;
use Modules\People\Models\Student;
use Modules\Welfare\Models\SafeguardingConcern;

/**
 * @return array{school: School, user: User}
 */
function com08Fixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $user = User::factory()->create();

    return compact('school', 'user');
}

it('stores no respondent identity for an anonymous survey, even if the caller supplies one (AC-COM-08-001)', function (): void {
    $f = com08Fixture();
    $survey = app(CreateSurveyAction::class)->execute(
        schoolId: $f['school']->id, title: 'Anonymous Feedback', purpose: 'feedback',
        audienceScope: 'whole_school', isAnonymous: true,
        questions: [['sequence' => 1, 'questionType' => 'text', 'prompt' => 'Anything else?']],
    );

    $response = app(SubmitSurveyResponseAction::class)->execute(new SubmitSurveyResponseData(
        surveyId: $survey->id, answersBySequence: [1 => 'The tuck shop queue is too long.'],
        respondentType: 'guardian', respondentId: 999,
    ));

    expect($response->respondent_type)->toBeNull()
        ->and($response->respondent_id)->toBeNull()
        ->and($response->isAnonymous())->toBeTrue();
});

it('drops any answer for a question a prior answer skipped, server-side (BR-COM-08-002)', function (): void {
    $f = com08Fixture();
    $survey = app(CreateSurveyAction::class)->execute(
        schoolId: $f['school']->id, title: 'Boarding Experience', purpose: 'feedback',
        audienceScope: 'whole_school', isAnonymous: false,
        questions: [
            ['sequence' => 1, 'questionType' => 'single_choice', 'prompt' => 'Are you a boarder?', 'skipLogic' => ['if_answer' => 'no', 'go_to_sequence' => 3]],
            ['sequence' => 2, 'questionType' => 'scale', 'prompt' => 'Rate your dormitory.'],
            ['sequence' => 3, 'questionType' => 'text', 'prompt' => 'Any other comments?'],
        ],
    );

    // Answered "no" to boarding but a tampered/buggy client still submits Q2 anyway.
    $response = app(SubmitSurveyResponseAction::class)->execute(new SubmitSurveyResponseData(
        surveyId: $survey->id,
        answersBySequence: [1 => 'no', 2 => 'excellent', 3 => 'All good.'],
        respondentType: 'guardian', respondentId: $f['user']->id,
    ));

    $storedSequences = SurveyResponseAnswer::where('response_id', $response->id)
        ->join('survey_questions', 'survey_questions.id', '=', 'survey_response_answers.question_id')
        ->pluck('survey_questions.sequence');

    expect($storedSequences)->toContain(1, 3)
        ->and($storedSequences)->not->toContain(2);
});

it('computes the SLA due date from the category at intake, visible on the complaint (AC-COM-08-002)', function (): void {
    $f = com08Fixture();
    Carbon::setTestNow('2027-01-01 09:00:00');
    $category = ComplaintCategory::factory()->for($f['school'])->create(['sla_hours' => 72]);

    $complaint = app(RaiseComplaintAction::class)->execute(new RaiseComplaintData(
        schoolId: $f['school']->id, categoryId: $category->id, raisedByType: 'guardian',
        subject: 'Late bus', description: 'The bus was an hour late.',
    ));

    expect($complaint->sla_due_at->toDateTimeString())->toBe('2027-01-04 09:00:00')
        ->and($complaint->status)->toBe('received');

    Carbon::setTestNow();
});

it('never includes an internal note in the raiser\'s own view of the thread (AC-COM-08-003)', function (): void {
    $f = com08Fixture();
    $category = ComplaintCategory::factory()->for($f['school'])->create();
    $complaint = app(RaiseComplaintAction::class)->execute(new RaiseComplaintData(
        schoolId: $f['school']->id, categoryId: $category->id, raisedByType: 'guardian',
        subject: 'Uniform issue', description: 'Wrong size delivered.',
    ));

    app(AddComplaintUpdateAction::class)->execute($complaint->id, 'comment', $f['user']->id, 'We are checking stock.', visibleToRaiser: true);
    app(AddComplaintUpdateAction::class)->execute($complaint->id, 'comment', $f['user']->id, 'Internal: supplier is unreliable, escalate to procurement.', visibleToRaiser: false);

    $raiserThread = app(GetComplaintThreadForRaiserAction::class)->execute($complaint->id);

    expect($raiserThread)->toHaveCount(1)
        ->and($raiserThread->first()->content)->toBe('We are checking stock.')
        ->and($raiserThread->pluck('content'))->not->toContain('Internal: supplier is unreliable, escalate to procurement.');
});

it('routes a complaint from a safeguarding-flagged category to BRD-08 instead of the ordinary queue (AC-COM-08-004, BR-COM-08-006)', function (): void {
    $f = com08Fixture();
    $category = ComplaintCategory::factory()->for($f['school'])->safeguarding()->create();
    $student = Student::factory()->for($f['school'])->create();

    $complaint = app(RaiseComplaintAction::class)->execute(new RaiseComplaintData(
        schoolId: $f['school']->id, categoryId: $category->id, raisedByType: 'staff',
        subject: 'Concerning disclosure', description: 'A learner disclosed something concerning.',
        relatedStudentId: $student->id, reporterUserId: $f['user']->id,
    ));

    expect($complaint->status)->toBe('escalated')
        ->and($complaint->safeguarding_concern_id)->not->toBeNull();

    $concern = SafeguardingConcern::findOrFail($complaint->safeguarding_concern_id);
    expect($concern->student_id)->toBe($student->id)
        ->and($concern->report_source)->toBe('complaint_system');
});

it('routes an ordinary-category complaint when the raiser explicitly flags a suspected safeguarding concern', function (): void {
    $f = com08Fixture();
    $category = ComplaintCategory::factory()->for($f['school'])->create();

    $complaint = app(RaiseComplaintAction::class)->execute(new RaiseComplaintData(
        schoolId: $f['school']->id, categoryId: $category->id, raisedByType: 'guardian',
        subject: 'Something is wrong', description: 'I am worried about my child.',
        suspectedSafeguardingConcern: true,
    ));

    expect($complaint->status)->toBe('escalated')
        ->and($complaint->safeguarding_concern_id)->not->toBeNull();
});

it('alerts the assignee once when a complaint approaches its SLA, and their manager once it breaches (BR-COM-08-004)', function (): void {
    $f = com08Fixture();
    NotificationKeyTemplateForCom08('comms.complaint_sla_approaching');
    NotificationKeyTemplateForCom08('comms.complaint_sla_breached');
    app(RegisterMessageGatewayAction::class)->execute(new RegisterMessageGatewayData(
        schoolId: $f['school']->id, channel: 'email', driver: 'smtp_relay', name: 'Test Email Gateway',
        credentials: 'test-key', createdByUserId: $f['user']->id,
    ));

    $manager = Staff::factory()->for($f['school'])->create(['user_id' => User::factory()->create()->id, 'work_email' => 'manager@example.com']);
    $assignee = Staff::factory()->for($f['school'])->create(['user_id' => User::factory()->create()->id, 'work_email' => 'assignee@example.com', 'reports_to_staff_id' => $manager->id]);
    $category = ComplaintCategory::factory()->for($f['school'])->create(['sla_hours' => 24]);

    Carbon::setTestNow('2027-01-01 09:00:00');
    $complaint = app(RaiseComplaintAction::class)->execute(new RaiseComplaintData(
        schoolId: $f['school']->id, categoryId: $category->id, raisedByType: 'guardian',
        subject: 'Fee query', description: 'Question about my invoice.',
    ));
    $complaint->update(['assigned_to_staff_id' => $assignee->id]);

    // Not yet approaching.
    $first = app(CheckComplaintSlaAction::class)->execute($complaint->id);
    expect($first)->toBe(['approaching' => false, 'breached' => false]);

    // Within the warning window (12h before a 24h SLA).
    Carbon::setTestNow('2027-01-02 02:00:00');
    app(CheckComplaintSlaAction::class)->execute($complaint->id);
    app(CheckComplaintSlaAction::class)->execute($complaint->id);
    expect(Notification::where('notification_key', 'comms.complaint_sla_approaching')->where('status', 'sent')->count())->toBe(1);

    // Past the due date — breached, alerts the manager instead.
    Carbon::setTestNow('2027-01-02 10:00:00');
    app(CheckComplaintSlaAction::class)->execute($complaint->id);
    app(CheckComplaintSlaAction::class)->execute($complaint->id);
    expect(Notification::where('notification_key', 'comms.complaint_sla_breached')->where('status', 'sent')->count())->toBe(1)
        ->and(Notification::where('notification_key', 'comms.complaint_sla_breached')->first()->recipient_id)->toBe($manager->user_id);

    Carbon::setTestNow();
});

it('records a decline as data in its own right, contributing to response-rate reporting (AC-COM-08-005)', function (): void {
    $f = com08Fixture();
    $student = Student::factory()->for($f['school'])->create();

    $interview = app(RequestExitInterviewAction::class)->execute($f['school']->id, $student->id);
    expect($interview->completed_at)->toBeNull();

    $declined = app(DeclineExitInterviewAction::class)->execute($interview->id);
    expect($declined->response_source)->toBe('declined')
        ->and($declined->completed_at)->not->toBeNull();
});

it('records a completed exit interview distinctly from a decline', function (): void {
    $f = com08Fixture();
    $student = Student::factory()->for($f['school'])->create();
    $interview = app(RequestExitInterviewAction::class)->execute($f['school']->id, $student->id);

    $completed = app(CompleteExitInterviewAction::class)->execute(
        $interview->id, primaryReason: 'relocation', responseSource: 'call', wouldRecommend: true,
    );

    expect($completed->response_source)->toBe('call')
        ->and($completed->primary_reason)->toBe('relocation')
        ->and($completed->would_recommend)->toBeTrue();
});

function NotificationKeyTemplateForCom08(string $key): void
{
    app(CreateNotificationTemplateAction::class)->execute(new CreateNotificationTemplateData(
        key: $key, channel: 'email', body: 'Complaint {{ complaint.number }} needs attention.',
    ));
}

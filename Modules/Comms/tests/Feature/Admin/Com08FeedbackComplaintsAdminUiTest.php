<?php

use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
use Modules\Comms\Domain\Actions\AddComplaintUpdateAction;
use Modules\Comms\Domain\Actions\CloseSurveyAction;
use Modules\Comms\Domain\Actions\CreateSurveyAction;
use Modules\Comms\Domain\Actions\RaiseComplaintAction;
use Modules\Comms\Domain\Actions\ResolveComplaintAction;
use Modules\Comms\Domain\Actions\SubmitSurveyResponseAction;
use Modules\Comms\Domain\DataObjects\RaiseComplaintData;
use Modules\Comms\Domain\DataObjects\SubmitSurveyResponseData;
use Modules\Comms\Livewire\Complaints\Categories;
use Modules\Comms\Livewire\Complaints\Queue;
use Modules\Comms\Livewire\Complaints\Show;
use Modules\Comms\Livewire\Complaints\Submit;
use Modules\Comms\Livewire\ExitInterviews\Index as ExitInterviewsIndex;
use Modules\Comms\Livewire\Surveys\Builder;
use Modules\Comms\Livewire\Surveys\Respond;
use Modules\Comms\Livewire\Surveys\Results;
use Modules\Comms\Models\Complaint;
use Modules\Comms\Models\ComplaintCategory;
use Modules\Comms\Models\ComplaintUpdate;
use Modules\Comms\Models\ExitInterview;
use Modules\Comms\Models\Survey;
use Modules\Comms\Models\SurveyQuestion;
use Modules\Comms\Models\SurveyResponse;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;
use Modules\People\Models\Guardian;
use Modules\People\Models\Staff;
use Modules\People\Models\Student;

/**
 * Book I COM-08 admin-UI pass. Own, distinctly-named helpers.
 *
 * @return array<string, mixed>
 */
function com08AdminFixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);

    return ['school' => $school];
}

/**
 * @param  array<string, mixed>  $f
 */
function com08AdminUser(array $f, string ...$permissionNames): User
{
    $user = User::factory()->create();
    $user->schools()->attach($f['school'], ['status' => 'active']);

    $grants = array_map(function (string $permissionName): PermissionGrantData {
        [$moduleCode, $action] = explode('.', $permissionName);

        $permission = Permission::firstOrCreate(
            ['name' => $permissionName],
            ['guard_name' => 'web', 'module_code' => strtoupper($moduleCode), 'resource' => $action, 'action' => $action],
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
function com08AdminComplaint(array $f, array $attributes = []): Complaint
{
    $category = ComplaintCategory::where('school_id', $f['school']->id)->first()
        ?? ComplaintCategory::factory()->for($f['school'])->create(['sla_hours' => 72]);

    return app(RaiseComplaintAction::class)->execute(new RaiseComplaintData(
        schoolId: $f['school']->id, categoryId: $category->id, raisedByType: 'guardian', raisedById: 1,
        subject: $attributes['subject'] ?? 'Fees query', description: $attributes['description'] ?? 'Charged twice for the trip.',
    ));
}

it('refuses every COM-08 screen to a user without its permission', function (string $component): void {
    $f = com08AdminFixture();
    $outsider = User::factory()->create();
    $outsider->schools()->attach($f['school'], ['status' => 'active']);

    Livewire::actingAs($outsider)->test($component, ['school' => $f['school']])->assertForbidden();
})->with([
    'builder' => Builder::class,
    'results' => Results::class,
    'queue' => Queue::class,
    'categories' => Categories::class,
    'exit interviews' => ExitInterviewsIndex::class,
]);

it('lets any school member open the complaint form but refuses a stranger to the school', function (): void {
    $f = com08AdminFixture();
    $member = User::factory()->create();
    $member->schools()->attach($f['school'], ['status' => 'active']);
    $stranger = User::factory()->create();

    Livewire::actingAs($member)->test(Submit::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($stranger)->test(Submit::class, ['school' => $f['school']])->assertForbidden();
});

it('renders every permissioned COM-08 screen for a fully-permissioned user', function (): void {
    $f = com08AdminFixture();
    $user = com08AdminUser($f, 'surveys.manage', 'surveys.view', 'complaints.manage');

    foreach ([Builder::class, Results::class, Queue::class, Categories::class, ExitInterviewsIndex::class, Submit::class] as $component) {
        Livewire::actingAs($user)->test($component, ['school' => $f['school']])->assertOk();
    }
});

it('creates a survey with typed questions and a forward skip rule, then closes it (BR-COM-08-002)', function (): void {
    $f = com08AdminFixture();
    $manager = com08AdminUser($f, 'surveys.manage');

    $component = Livewire::actingAs($manager)->test(Builder::class, ['school' => $f['school']])
        ->set('title', 'Term 1 satisfaction')
        ->set('isAnonymous', true)
        ->set('questions', [
            ['type' => 'single_choice', 'prompt' => 'Do you use the bus?', 'options' => "Yes\nNo", 'required' => true, 'skipIf' => 'No', 'skipTo' => '3'],
            ['type' => 'text', 'prompt' => 'Which route?', 'options' => '', 'required' => false, 'skipIf' => '', 'skipTo' => ''],
            ['type' => 'nps', 'prompt' => 'Recommend us?', 'options' => '', 'required' => true, 'skipIf' => '', 'skipTo' => ''],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $survey = Survey::where('school_id', $f['school']->id)->firstOrFail();
    $questions = SurveyQuestion::where('survey_id', $survey->id)->orderBy('sequence')->get();

    expect($survey->is_anonymous)->toBeTrue()->and($survey->status)->toBe('open')
        ->and($questions)->toHaveCount(3)
        ->and($questions[0]->options)->toBe(['Yes', 'No'])
        ->and($questions[0]->skip_logic)->toBe(['if_answer' => 'No', 'go_to_sequence' => 3])
        ->and($questions[2]->options)->toBeNull();

    $component->call('close', $survey->id);
    expect($survey->fresh()->status)->toBe('closed');
});

it('refuses a backward skip rule, a one-option choice question and a survey with no questions', function (): void {
    $f = com08AdminFixture();
    $manager = com08AdminUser($f, 'surveys.manage');

    $component = Livewire::actingAs($manager)->test(Builder::class, ['school' => $f['school']])->set('title', 'Bad survey');

    $component->set('questions', [
        ['type' => 'single_choice', 'prompt' => 'Q1', 'options' => "Yes\nNo", 'required' => true, 'skipIf' => 'Yes', 'skipTo' => '1'],
        ['type' => 'text', 'prompt' => 'Q2', 'options' => '', 'required' => false, 'skipIf' => '', 'skipTo' => ''],
    ])->call('save')->assertHasErrors(['questions.0.skipTo']);

    $component->set('questions', [['type' => 'single_choice', 'prompt' => 'Q1', 'options' => 'Only one', 'required' => true, 'skipIf' => '', 'skipTo' => '']])
        ->call('save')->assertHasErrors(['questions.0.options']);

    $component->set('questions', [])->call('save')->assertHasErrors(['questions']);

    expect(Survey::where('school_id', $f['school']->id)->count())->toBe(0);
});

it('aggregates answers without ever exposing a respondent (AC-COM-08-001)', function (): void {
    $f = com08AdminFixture();
    $viewer = com08AdminUser($f, 'surveys.view');
    $survey = app(CreateSurveyAction::class)->execute(
        schoolId: $f['school']->id, title: 'Anon', purpose: 'satisfaction', audienceScope: 'whole_school', isAnonymous: true,
        questions: [
            ['sequence' => 1, 'questionType' => 'single_choice', 'prompt' => 'Bus?', 'options' => ['Yes', 'No']],
            ['sequence' => 2, 'questionType' => 'nps', 'prompt' => 'Recommend?'],
            ['sequence' => 3, 'questionType' => 'text', 'prompt' => 'Comments'],
        ],
    );

    foreach ([[['Yes'], 10, 'Great'], [['Yes'], 9, 'Good'], [['No'], 3, 'Poor']] as $i => [$choice, $nps, $text]) {
        app(SubmitSurveyResponseAction::class)->execute(new SubmitSurveyResponseData(
            surveyId: $survey->id, answersBySequence: [1 => $choice[0], 2 => $nps, 3 => $text],
            respondentType: 'guardian', respondentId: $i + 1,
        ));
    }

    $component = Livewire::actingAs($viewer)->test(Results::class, ['school' => $f['school']])->set('surveyId', $survey->id);
    $summaries = collect($component->viewData('summaries'))->keyBy('sequence');

    expect($summaries[1]['counts'])->toBe(['Yes' => 2, 'No' => 1])
        ->and($summaries[2]['nps'])->toBe(33)
        ->and($summaries[3]['texts'])->toContain('Great', 'Poor');
    $component->assertSee('anonymous survey');
    expect(SurveyResponse::where('survey_id', $survey->id)->whereNotNull('respondent_id')->count())->toBe(0);
});

it('creates complaint categories, refusing a duplicate code', function (): void {
    $f = com08AdminFixture();
    $manager = com08AdminUser($f, 'complaints.manage');

    $component = Livewire::actingAs($manager)->test(Categories::class, ['school' => $f['school']])
        ->set('code', 'fees')->set('name', 'Fees')->set('slaHours', 48)
        ->call('create')->assertHasNoErrors();

    expect(ComplaintCategory::where('school_id', $f['school']->id)->firstOrFail()->sla_hours)->toBe(48);

    $component->set('code', 'fees')->set('name', 'Fees again')->call('create')->assertHasErrors(['code']);
});

it('sets the SLA deadline at intake and shows it to the raiser (AC-COM-08-002, BR-COM-08-003)', function (): void {
    $f = com08AdminFixture();
    $staffUser = com08AdminUser($f);
    $staff = Staff::factory()->for($f['school'])->create(['user_id' => $staffUser->id]);
    $category = ComplaintCategory::factory()->for($f['school'])->create(['sla_hours' => 72]);

    $component = Livewire::actingAs($staffUser)->test(Submit::class, ['school' => $f['school']])
        ->set('categoryId', $category->id)->set('subject', 'Broken desk')->set('description', 'The desk in 3B is broken.')
        ->call('submit')->assertHasNoErrors();

    $complaint = Complaint::where('school_id', $f['school']->id)->firstOrFail();
    expect($complaint->raised_by_type)->toBe('staff')->and($complaint->raised_by_id)->toBe($staff->id)
        ->and((int) round(now()->diffInHours($complaint->sla_due_at, false)))->toBe(72)
        ->and($component->get('submittedNumber'))->toBe($complaint->complaint_number)
        ->and($component->get('submittedDue'))->not->toBeNull();
});

it('stores no identity for an anonymous complaint', function (): void {
    $f = com08AdminFixture();
    $user = com08AdminUser($f);
    Staff::factory()->for($f['school'])->create(['user_id' => $user->id]);
    $category = ComplaintCategory::factory()->for($f['school'])->create();

    Livewire::actingAs($user)->test(Submit::class, ['school' => $f['school']])
        ->set('categoryId', $category->id)->set('subject', 'Concern')->set('description', 'Details.')->set('anonymous', true)
        ->call('submit')->assertHasNoErrors();

    $complaint = Complaint::where('school_id', $f['school']->id)->firstOrFail();
    expect($complaint->raised_by_type)->toBe('anonymous')->and($complaint->raised_by_id)->toBeNull();
});

it('never shows the raiser an internal note on their own complaint (AC-COM-08-003, BR-COM-08-005)', function (): void {
    $f = com08AdminFixture();
    $user = com08AdminUser($f);
    $staff = Staff::factory()->for($f['school'])->create(['user_id' => $user->id]);
    $category = ComplaintCategory::factory()->for($f['school'])->create();
    $complaint = app(RaiseComplaintAction::class)->execute(new RaiseComplaintData(
        schoolId: $f['school']->id, categoryId: $category->id, raisedByType: 'staff', raisedById: $staff->id,
        subject: 'Lab equipment', description: 'Broken microscope.',
    ));
    app(AddComplaintUpdateAction::class)->execute($complaint->id, 'comment', $user->id, 'INTERNAL-ONLY-NOTE', visibleToRaiser: false);
    app(AddComplaintUpdateAction::class)->execute($complaint->id, 'comment', $user->id, 'We are looking into it.', visibleToRaiser: true);

    Livewire::actingAs($user)->test(Submit::class, ['school' => $f['school']])
        ->assertSee('We are looking into it.')
        ->assertDontSee('INTERNAL-ONLY-NOTE');
});

it('refers a flagged complaint to safeguarding and never shows its content in the queue or detail (AC-COM-08-004, BR-COM-08-006)', function (): void {
    $f = com08AdminFixture();
    $manager = com08AdminUser($f, 'complaints.manage');
    $category = ComplaintCategory::factory()->for($f['school'])->create();
    $complaint = app(RaiseComplaintAction::class)->execute(new RaiseComplaintData(
        schoolId: $f['school']->id, categoryId: $category->id, raisedByType: 'guardian', raisedById: 1,
        subject: 'SECRET-SUBJECT', description: 'SECRET-DISCLOSURE-TEXT', suspectedSafeguardingConcern: true,
    ));
    expect($complaint->isRoutedToSafeguarding())->toBeTrue()->and($complaint->status)->toBe('escalated');
    SchoolContext::set($f['school']);

    Livewire::actingAs($manager)->test(Queue::class, ['school' => $f['school']])
        ->assertSee($complaint->complaint_number)
        ->assertSee('Referred to safeguarding')
        ->assertDontSee('SECRET-SUBJECT')
        ->assertDontSee('SECRET-DISCLOSURE-TEXT');

    $show = Livewire::actingAs($manager)->test(Show::class, ['school' => $f['school'], 'complaint' => $complaint->ulid])
        ->assertSee('referred to the safeguarding team')
        ->assertDontSee('SECRET-SUBJECT')
        ->assertDontSee('SECRET-DISCLOSURE-TEXT');

    $show->set('note', 'x')->call('post')->assertForbidden();
});

it('lists open complaints by deadline and highlights an overdue one (BR-COM-08-004)', function (): void {
    $f = com08AdminFixture();
    $manager = com08AdminUser($f, 'complaints.manage');
    $overdue = com08AdminComplaint($f, ['subject' => 'Overdue matter']);
    $overdue->update(['sla_due_at' => now()->subDay()]);
    com08AdminComplaint($f, ['subject' => 'Fresh matter']);
    $resolved = com08AdminComplaint($f, ['subject' => 'Done matter']);
    $resolved->update(['status' => 'resolved']);
    SchoolContext::set($f['school']);

    $component = Livewire::actingAs($manager)->test(Queue::class, ['school' => $f['school']])
        ->assertSee('Overdue matter')->assertSee('Fresh matter')->assertDontSee('Done matter')->assertSee('table-danger');

    $component->set('statusFilter', 'overdue')->assertSee('Overdue matter')->assertDontSee('Fresh matter');
});

it('separates the raiser-visible thread from internal notes on the detail screen (AC-COM-08-003)', function (): void {
    $f = com08AdminFixture();
    $manager = com08AdminUser($f, 'complaints.manage');
    $complaint = com08AdminComplaint($f);

    SchoolContext::set($f['school']);
    $component = Livewire::actingAs($manager)->test(Show::class, ['school' => $f['school'], 'complaint' => $complaint->ulid])
        ->set('note', 'Needs a bursar check')->call('post')->assertHasNoErrors()
        ->set('note', 'We are on it')->set('visibleToRaiser', true)->call('post')->assertHasNoErrors();

    expect($component->viewData('raiserThread')->pluck('content')->all())->toBe(['We are on it'])
        ->and($component->viewData('internalNotes')->pluck('content')->all())->toBe(['Needs a bursar check']);
});

it('lets the assignee work a complaint without complaints.manage, and refuses a stranger', function (): void {
    $f = com08AdminFixture();
    $assigneeUser = com08AdminUser($f);
    $strangerUser = com08AdminUser($f);
    $assignee = Staff::factory()->for($f['school'])->create(['user_id' => $assigneeUser->id]);
    $complaint = com08AdminComplaint($f);
    $complaint->update(['assigned_to_staff_id' => $assignee->id]);
    SchoolContext::set($f['school']);

    $show = Livewire::actingAs($assigneeUser)->test(Show::class, ['school' => $f['school'], 'complaint' => $complaint->ulid])->assertOk();
    $show->set('note', 'Called the parent')->call('post')->assertHasNoErrors();
    $show->set('assigneeId', $assignee->id)->call('assign')->assertForbidden();

    Livewire::actingAs($strangerUser)->test(Show::class, ['school' => $f['school'], 'complaint' => $complaint->ulid])->assertForbidden();
});

it('assigns, moves through statuses with a visible trail, and resolves (BR-COM-08-003/004)', function (): void {
    $f = com08AdminFixture();
    $manager = com08AdminUser($f, 'complaints.manage');
    $staff = Staff::factory()->for($f['school'])->create();
    $complaint = com08AdminComplaint($f);
    SchoolContext::set($f['school']);

    $show = Livewire::actingAs($manager)->test(Show::class, ['school' => $f['school'], 'complaint' => $complaint->ulid]);

    $show->set('assigneeId', $staff->id)->call('assign')->assertHasNoErrors();
    expect($complaint->fresh()->assigned_to_staff_id)->toBe($staff->id)->and($complaint->fresh()->status)->toBe('acknowledged');
    expect(ComplaintUpdate::where('complaint_id', $complaint->id)->where('update_type', 'reassignment')->firstOrFail()->visible_to_raiser)->toBeFalse();

    SchoolContext::clear();
    $show->call('setStatus', 'investigating');
    expect($complaint->fresh()->status)->toBe('investigating')
        ->and(ComplaintUpdate::where('complaint_id', $complaint->id)->where('update_type', 'status_change')->firstOrFail()->visible_to_raiser)->toBeTrue();

    $show->set('resolution', 'Refund issued')->call('resolve')->assertHasNoErrors();
    expect($complaint->fresh()->status)->toBe('resolved')->and($complaint->fresh()->resolution)->toBe('Refund issued');

    $show->call('setStatus', 'investigating')->assertDispatched('toast', variant: 'danger');
    expect($complaint->fresh()->status)->toBe('resolved');
});

it('404s a complaint belonging to another school', function (): void {
    $f = com08AdminFixture();
    $manager = com08AdminUser($f, 'complaints.manage');
    $other = School::factory()->create();
    $foreign = Complaint::factory()->create(['school_id' => $other->id]);
    SchoolContext::set($f['school']);

    expect(fn () => Livewire::actingAs($manager)->test(Show::class, ['school' => $f['school'], 'complaint' => $foreign->ulid]))
        ->toThrow(ModelNotFoundException::class);
});

it('offers, completes and declines exit interviews, counting the decline in the response rate (AC-COM-08-005, BR-COM-08-007)', function (): void {
    $f = com08AdminFixture();
    $manager = com08AdminUser($f, 'complaints.manage');
    $leaver = Student::factory()->for($f['school'])->create();
    $other = Student::factory()->for($f['school'])->create();

    $component = Livewire::actingAs($manager)->test(ExitInterviewsIndex::class, ['school' => $f['school']])
        ->set('admissionNumber', $leaver->admission_number)->call('request')->assertHasNoErrors();

    $component->set('admissionNumber', $leaver->admission_number)->call('request')->assertHasErrors(['admissionNumber']);
    $component->set('admissionNumber', 'NOPE')->call('request')->assertHasErrors(['admissionNumber']);

    $first = ExitInterview::where('student_id', $leaver->id)->firstOrFail();
    $component->set('completingId', $first->id)->set('primaryReason', 'relocation')->set('responseSource', 'call')
        ->set('wouldRecommend', 'yes')->call('complete')->assertHasNoErrors();
    expect($first->fresh()->primary_reason)->toBe('relocation')->and($first->fresh()->would_recommend)->toBeTrue();

    $component->set('admissionNumber', $other->admission_number)->call('request');
    $second = ExitInterview::where('student_id', $other->id)->firstOrFail();
    $component->call('decline', $second->id);

    expect($second->fresh()->wasDeclined())->toBeTrue()
        ->and($component->viewData('declineRate'))->toBe(50)
        ->and($component->viewData('completed'))->toBe(1)
        ->and($component->viewData('declined'))->toBe(1);

    // Only a still-pending request can be completed or declined.
    expect(fn () => $component->set('completingId', $second->id)->set('primaryReason', 'fees')->call('complete'))->toThrow(ModelNotFoundException::class);
    expect(fn () => $component->call('decline', $first->id))->toThrow(ModelNotFoundException::class);
});

it('lets a guardian answer an identified survey once, and keeps staff-only surveys from them (BR-COM-08-001)', function (): void {
    $f = com08AdminFixture();
    $user = com08AdminUser($f);
    $guardian = Guardian::factory()->for($f['school'])->create(['user_id' => $user->id]);
    $survey = app(CreateSurveyAction::class)->execute(
        schoolId: $f['school']->id, title: 'Term feedback', purpose: 'feedback', audienceScope: 'whole_school', isAnonymous: false,
        questions: [
            ['sequence' => 1, 'questionType' => 'single_choice', 'prompt' => 'Happy?', 'options' => ['Yes', 'No'], 'skipLogic' => ['if_answer' => 'Yes', 'go_to_sequence' => 3]],
            ['sequence' => 2, 'questionType' => 'text', 'prompt' => 'Why not?'],
            ['sequence' => 3, 'questionType' => 'nps', 'prompt' => 'Recommend?'],
        ],
    );
    $staffOnly = app(CreateSurveyAction::class)->execute(
        schoolId: $f['school']->id, title: 'Staff pulse', purpose: 'feedback', audienceScope: 'staff', isAnonymous: false,
        questions: [['sequence' => 1, 'questionType' => 'text', 'prompt' => 'Mood?']],
    );

    $component = Livewire::actingAs($user)->test(Respond::class, ['school' => $f['school']])
        ->assertSee('Term feedback')
        ->assertDontSee('Staff pulse')
        ->call('open', $survey->id)
        ->set('answers.1', 'Yes')
        ->set('answers.3', '9')
        ->call('submit')
        ->assertSet('surveyId', null);

    expect(SurveyResponse::where('survey_id', $survey->id)->where('respondent_type', 'guardian')->where('respondent_id', $guardian->id)->count())->toBe(1);

    $component->assertDontSee('Term feedback');

    Livewire::actingAs($user)->test(Respond::class, ['school' => $f['school']])
        ->set('surveyId', $staffOnly->id)
        ->call('submit');

    expect(SurveyResponse::where('survey_id', $staffOnly->id)->count())->toBe(0);
});

it('refuses a required question left blank and an answer that is not one of the options', function (): void {
    $f = com08AdminFixture();
    $user = com08AdminUser($f);
    Guardian::factory()->for($f['school'])->create(['user_id' => $user->id]);
    $survey = app(CreateSurveyAction::class)->execute(
        schoolId: $f['school']->id, title: 'Bus survey', purpose: 'feedback', audienceScope: 'whole_school', isAnonymous: true,
        questions: [['sequence' => 1, 'questionType' => 'single_choice', 'prompt' => 'Bus?', 'options' => ['Yes', 'No']]],
    );

    $component = Livewire::actingAs($user)->test(Respond::class, ['school' => $f['school']])->call('open', $survey->id)->call('submit');
    expect(SurveyResponse::where('survey_id', $survey->id)->count())->toBe(0);

    $component->set('answers.1', 'Maybe')->call('submit');
    expect(SurveyResponse::where('survey_id', $survey->id)->count())->toBe(0);

    $component->set('answers.1', 'No')->call('submit');
    expect(SurveyResponse::where('survey_id', $survey->id)->count())->toBe(1);
});

it('refuses a response once the survey is closed', function (): void {
    $f = com08AdminFixture();
    $survey = app(CreateSurveyAction::class)->execute(
        schoolId: $f['school']->id, title: 'Closed', purpose: 'feedback', audienceScope: 'whole_school', isAnonymous: true,
        questions: [['sequence' => 1, 'questionType' => 'text', 'prompt' => 'Thoughts?']],
    );
    app(CloseSurveyAction::class)->execute($survey->id);

    expect(fn () => app(SubmitSurveyResponseAction::class)->execute(new SubmitSurveyResponseData(surveyId: $survey->id, answersBySequence: [1 => 'late'])))
        ->toThrow(InvalidStateTransitionException::class);
});

it('lets only the raiser rate a resolved complaint, once (BR-COM-08-008)', function (): void {
    $f = com08AdminFixture();
    $user = com08AdminUser($f);
    $guardian = Guardian::factory()->for($f['school'])->create(['user_id' => $user->id]);
    $category = ComplaintCategory::factory()->for($f['school'])->create(['sla_hours' => 72]);
    $complaint = app(RaiseComplaintAction::class)->execute(new RaiseComplaintData(
        schoolId: $f['school']->id, categoryId: $category->id, raisedByType: 'guardian', raisedById: $guardian->id,
        subject: 'Fees query', description: 'Charged twice.',
    ));

    Livewire::actingAs($user)->test(Submit::class, ['school' => $f['school']])->call('rate', $complaint->id, 5);
    expect($complaint->fresh()->satisfaction_rating)->toBeNull();

    app(ResolveComplaintAction::class)->execute($complaint->id, 'Refunded.');

    $stranger = com08AdminUser($f);
    Guardian::factory()->for($f['school'])->create(['user_id' => $stranger->id]);
    Livewire::actingAs($stranger)->test(Submit::class, ['school' => $f['school']])->call('rate', $complaint->id, 1);
    expect($complaint->fresh()->satisfaction_rating)->toBeNull();

    $component = Livewire::actingAs($user)->test(Submit::class, ['school' => $f['school']])->call('rate', $complaint->id, 4);
    expect($complaint->fresh()->satisfaction_rating)->toBe(4);

    $component->call('rate', $complaint->id, 1);
    expect($complaint->fresh()->satisfaction_rating)->toBe(4);
});

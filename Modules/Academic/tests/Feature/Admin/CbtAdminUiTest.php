<?php

use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
use Modules\Academic\Domain\Actions\AutoSubmitExpiredAttemptsAction;
use Modules\Academic\Domain\Actions\CreateQuestionBankItemAction;
use Modules\Academic\Domain\Actions\RecordFocusEventAction;
use Modules\Academic\Domain\Actions\SaveResponseAction;
use Modules\Academic\Domain\Actions\StartAttemptAction;
use Modules\Academic\Domain\Actions\SubmitAttemptAction;
use Modules\Academic\Domain\DataObjects\CreateQuestionBankItemData;
use Modules\Academic\Domain\DataObjects\RecordFocusEventData;
use Modules\Academic\Domain\DataObjects\SaveResponseData;
use Modules\Academic\Domain\DataObjects\StartAttemptData;
use Modules\Academic\Domain\DataObjects\SubmitAttemptData;
use Modules\Academic\Livewire\Cbt\Bank;
use Modules\Academic\Livewire\Cbt\Builder;
use Modules\Academic\Livewire\Cbt\ItemAnalysis;
use Modules\Academic\Livewire\Cbt\ManualMarking;
use Modules\Academic\Livewire\Cbt\Monitor;
use Modules\Academic\Models\CbtCandidateAttempt;
use Modules\Academic\Models\CbtResponse;
use Modules\Academic\Models\CbtTest;
use Modules\Academic\Models\CurriculumFramework;
use Modules\Academic\Models\QuestionBankItem;
use Modules\Academic\Models\Subject;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\People\Models\Student;

/**
 * Book K ACA-09 admin-UI pass. Own, distinctly-named helpers.
 *
 * @return array<string, mixed>
 */
function cbtAdminFixture(): array
{
    $school = School::factory()->create();
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create(['is_current' => true, 'starts_on' => now()->subMonth()]);
    $framework = CurriculumFramework::factory()->for($school)->create();
    $subject = Subject::factory()->for($school)->create(['framework_id' => $framework->id, 'name' => 'Physics']);

    return compact('school', 'year', 'term', 'subject');
}

/**
 * @param  array<string, mixed>  $f
 */
function cbtAdminUser(array $f, string ...$permissionNames): User
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
function cbtAdminMcq(array $f, string $difficulty = 'medium'): QuestionBankItem
{
    return app(CreateQuestionBankItemAction::class)->execute(new CreateQuestionBankItemData(
        schoolId: $f['school']->id, subjectId: $f['subject']->id, itemType: 'mcq', difficulty: $difficulty, prompt: 'SI unit of force?',
        maxMark: 2, isAutoMarkable: true, createdByUserId: User::factory()->create()->id, options: ['Newton', 'Joule', 'Watt'], correctAnswer: [0],
    ));
}

/**
 * @param  array<string, mixed>  $f
 * @param  array<string, mixed>  $overrides
 */
function cbtAdminTest(array $f, array $questionIds, array $overrides = []): CbtTest
{
    return CbtTest::factory()->create($overrides + [
        'school_id' => $f['school']->id, 'term_id' => $f['term']->id, 'subject_id' => $f['subject']->id,
        'question_ids' => $questionIds, 'duration_minutes' => 30, 'opens_at' => now()->subHour(), 'closes_at' => now()->addDay(), 'status' => 'scheduled',
    ]);
}

it('refuses every CBT screen to a user without its permission', function (string $component): void {
    $f = cbtAdminFixture();
    $outsider = User::factory()->create();
    $outsider->schools()->attach($f['school'], ['status' => 'active']);

    Livewire::actingAs($outsider)->test($component, ['school' => $f['school']])->assertForbidden();
})->with([Bank::class, Builder::class, Monitor::class, ManualMarking::class, ItemAnalysis::class]);

it('adds an objective question with its correct answer and refuses one that cannot mark itself', function (): void {
    $f = cbtAdminFixture();
    $user = cbtAdminUser($f, 'cbt.bank.manage');

    $component = Livewire::actingAs($user)->test(Bank::class, ['school' => $f['school']])
        ->set('subjectId', $f['subject']->id)->set('prompt', 'Which is a vector?')->set('options', "Speed\nVelocity\nMass")->set('correct', '5')->call('create')->assertHasErrors('correct');
    expect(QuestionBankItem::count())->toBe(0);

    $component->set('correct', '1')->call('create')->assertHasNoErrors();
    expect(QuestionBankItem::firstOrFail())->is_auto_markable->toBeTrue()->correct_answer->toBe([1])->options->toBe(['Speed', 'Velocity', 'Mass']);

    $component->set('itemType', 'essay')->set('prompt', 'Discuss momentum.')->set('maxMark', '10')->call('create')->assertHasNoErrors();
    expect(QuestionBankItem::where('item_type', 'essay')->firstOrFail()->is_auto_markable)->toBeFalse();
});

it('refuses a question whose type contradicts how it is marked (Action level)', function (): void {
    $f = cbtAdminFixture();
    $make = fn (string $type, bool $auto, ?array $correct = null, ?array $options = null) => new CreateQuestionBankItemData(
        schoolId: $f['school']->id, subjectId: $f['subject']->id, itemType: $type, difficulty: 'easy', prompt: 'Q', maxMark: 1, isAutoMarkable: $auto, createdByUserId: 1, correctAnswer: $correct, options: $options,
    );

    expect(fn () => app(CreateQuestionBankItemAction::class)->execute($make('essay', true, ['x'])))->toThrow(InvalidArgumentException::class);
    expect(fn () => app(CreateQuestionBankItemAction::class)->execute($make('mcq', false, [0], ['a', 'b'])))->toThrow(InvalidArgumentException::class);
    expect(fn () => app(CreateQuestionBankItemAction::class)->execute($make('mcq', true, null, ['a', 'b'])))->toThrow(InvalidArgumentException::class);
    expect(fn () => app(CreateQuestionBankItemAction::class)->execute($make('mcq', true, [0], ['only one'])))->toThrow(InvalidArgumentException::class);
    expect(fn () => app(CreateQuestionBankItemAction::class)->execute($make('riddle', true, [0])))->toThrow(InvalidArgumentException::class);
    expect(QuestionBankItem::count())->toBe(0);
});

it('retires a question from new tests without touching tests already built', function (): void {
    $f = cbtAdminFixture();
    $user = cbtAdminUser($f, 'cbt.bank.manage');
    $question = cbtAdminMcq($f);
    $test = cbtAdminTest($f, [$question->id]);

    Livewire::actingAs($user)->test(Bank::class, ['school' => $f['school']])->call('setActive', $question->id, false);

    expect($question->fresh()->is_active)->toBeFalse()->and($test->fresh()->question_ids)->toBe([$question->id]);
});

it('builds a test by hand from this subject’s active questions only, and shows what the bank holds', function (): void {
    $f = cbtAdminFixture();
    $user = cbtAdminUser($f, 'cbt.test.manage');
    $mine = cbtAdminMcq($f);
    $retired = cbtAdminMcq($f);
    $retired->update(['is_active' => false]);
    $otherSubject = Subject::factory()->for($f['school'])->create(['framework_id' => CurriculumFramework::factory()->for($f['school'])->create(['code' => 'OTHER_FW'])->id]);
    $foreign = QuestionBankItem::factory()->create(['school_id' => $f['school']->id, 'subject_id' => $otherSubject->id]);

    $component = Livewire::actingAs($user)->test(Builder::class, ['school' => $f['school']])
        ->set('title', 'Forces test')->set('subjectId', $f['subject']->id)->set('opensAt', now()->addDay()->format('Y-m-d\TH:i'))->set('closesAt', now()->addDays(2)->format('Y-m-d\TH:i'))
        ->assertSee('medium 1');

    $component->set('questionIds', [$mine->id, $retired->id])->call('create')->assertHasErrors('title');
    $component->set('questionIds', [$mine->id, $foreign->id])->call('create')->assertHasErrors('title');
    expect(CbtTest::count())->toBe(0);

    $component->set('questionIds', [$mine->id])->call('create')->assertHasNoErrors();
    expect(CbtTest::firstOrFail())->status->toBe('draft')->question_ids->toBe([$mine->id]);
});

it('fails rule-based assembly loudly when the bank is short, and when the mix is not 100%', function (): void {
    $f = cbtAdminFixture();
    $user = cbtAdminUser($f, 'cbt.test.manage');
    cbtAdminMcq($f, 'easy');

    $component = Livewire::actingAs($user)->test(Builder::class, ['school' => $f['school']])
        ->set('title', 'Rule test')->set('subjectId', $f['subject']->id)->set('method', 'rule_based')->set('count', '10')
        ->set('opensAt', now()->addDay()->format('Y-m-d\TH:i'))->set('closesAt', now()->addDays(2)->format('Y-m-d\TH:i'));

    $component->set('easyPercent', '50')->set('mediumPercent', '30')->set('hardPercent', '10')->call('create')->assertHasErrors('easyPercent');
    $component->set('hardPercent', '20')->call('create')->assertHasErrors('title');
    expect(CbtTest::count())->toBe(0);
});

it('refuses a test that closes before it opens, and walks a draft through schedule → close → release', function (): void {
    $f = cbtAdminFixture();
    $user = cbtAdminUser($f, 'cbt.test.manage');
    $question = cbtAdminMcq($f);

    $component = Livewire::actingAs($user)->test(Builder::class, ['school' => $f['school']])
        ->set('title', 'Lifecycle')->set('subjectId', $f['subject']->id)->set('questionIds', [$question->id])
        ->set('opensAt', now()->addDays(2)->format('Y-m-d\TH:i'))->set('closesAt', now()->addDay()->format('Y-m-d\TH:i'))->call('create')->assertHasErrors('title');
    expect(CbtTest::count())->toBe(0);

    $component->set('opensAt', now()->subHour()->format('Y-m-d\TH:i'))->set('closesAt', now()->addDay()->format('Y-m-d\TH:i'))->call('create')->assertHasNoErrors();
    $test = CbtTest::firstOrFail();

    $component->call('publish', $test->id);
    expect($test->fresh()->status)->toBe('draft');

    $component->call('schedule', $test->id);
    expect($test->fresh()->status)->toBe('scheduled');

    $component->call('close', $test->id);
    expect($test->fresh()->status)->toBe('closed');

    $component->call('publish', $test->id);
    expect($test->fresh()->status)->toBe('results_released');
});

it('refuses to accept answers after time has run out, and auto-submits an expired attempt with what it saved (AC-ACA-09-006)', function (): void {
    $f = cbtAdminFixture();
    $question = cbtAdminMcq($f);
    $test = cbtAdminTest($f, [$question->id], ['duration_minutes' => 10]);
    $student = Student::factory()->for($f['school'])->create();

    $attempt = app(StartAttemptAction::class)->execute(new StartAttemptData($test->id, $student->id));
    app(SaveResponseAction::class)->execute(new SaveResponseData($attempt->id, $question->id, [0]));
    $attempt->update(['started_at' => now()->subMinutes(20)]);

    expect(fn () => app(SaveResponseAction::class)->execute(new SaveResponseData($attempt->id, $question->id, [1])))->toThrow(InvalidStateTransitionException::class);

    $swept = app(AutoSubmitExpiredAttemptsAction::class)->execute($test->id);

    expect($swept)->toBe(1)->and($attempt->fresh())->auto_submitted->toBeTrue()->status->toBe('auto_marked')
        ->and((float) $attempt->fresh()->raw_mark)->toBe(2.0);
});

it('refuses an answer to a question that is not on the test', function (): void {
    $f = cbtAdminFixture();
    $question = cbtAdminMcq($f);
    $other = cbtAdminMcq($f);
    $test = cbtAdminTest($f, [$question->id]);
    $attempt = app(StartAttemptAction::class)->execute(new StartAttemptData($test->id, Student::factory()->for($f['school'])->create()->id));

    expect(fn () => app(SaveResponseAction::class)->execute(new SaveResponseData($attempt->id, $other->id, [0])))->toThrow(InvalidArgumentException::class);
});

it('shows server-side time and flags tab switches over the limit without penalising (AC-ACA-09-005)', function (): void {
    $f = cbtAdminFixture();
    $user = cbtAdminUser($f, 'cbt.test.monitor');
    $question = cbtAdminMcq($f);
    $test = cbtAdminTest($f, [$question->id], ['browser_focus_monitoring' => true, 'max_tab_switches' => 3, 'duration_minutes' => 30]);
    $student = Student::factory()->for($f['school'])->create(['first_name' => 'Tapiwa', 'last_name' => 'Switcher']);
    $attempt = app(StartAttemptAction::class)->execute(new StartAttemptData($test->id, $student->id));

    foreach (range(1, 5) as $i) {
        app(RecordFocusEventAction::class)->execute(new RecordFocusEventData($attempt->id, 'tab_switch'));
    }

    Livewire::actingAs($user)->test(Monitor::class, ['school' => $f['school']])->set('testId', $test->id)->assertSee('Tapiwa Switcher')->assertSee('Review')->assertSee('29:');

    expect($attempt->fresh())->tab_switch_count->toBe(5)->raw_mark->toBeNull()
        ->and(fn () => app(RecordFocusEventAction::class)->execute(new RecordFocusEventData($attempt->id, 'zoomed')))->toThrow(InvalidArgumentException::class);
});

it('queues written answers for manual marking, bounds the mark, and never offers an auto-marked item', function (): void {
    $f = cbtAdminFixture();
    $marker = cbtAdminUser($f, 'cbt.mark');
    $mcq = cbtAdminMcq($f);
    $essay = app(CreateQuestionBankItemAction::class)->execute(new CreateQuestionBankItemData(
        schoolId: $f['school']->id, subjectId: $f['subject']->id, itemType: 'essay', difficulty: 'hard', prompt: 'Discuss momentum.', maxMark: 10, isAutoMarkable: false, createdByUserId: 1,
    ));
    $test = cbtAdminTest($f, [$mcq->id, $essay->id]);
    $student = Student::factory()->for($f['school'])->create();
    $attempt = app(StartAttemptAction::class)->execute(new StartAttemptData($test->id, $student->id));
    app(SaveResponseAction::class)->execute(new SaveResponseData($attempt->id, $mcq->id, [0]));
    app(SaveResponseAction::class)->execute(new SaveResponseData($attempt->id, $essay->id, 'Momentum is mass times velocity.'));
    app(SubmitAttemptAction::class)->execute(new SubmitAttemptData($attempt->id));

    $written = CbtResponse::where('question_id', $essay->id)->firstOrFail();
    $objective = CbtResponse::where('question_id', $mcq->id)->firstOrFail();

    $component = Livewire::actingAs($marker)->test(ManualMarking::class, ['school' => $f['school']])->set('testId', $test->id)->assertSee('Momentum is mass times velocity.')->assertDontSee('SI unit of force');

    $component->call('begin', $written->id)->set('mark', '15')->call('save')->assertHasErrors('mark');
    $component->set('mark', '7.5')->set('feedback', 'Good, but define the units.')->call('save')->assertHasNoErrors();

    expect($written->fresh())->mark_awarded->toEqual(7.5)->marked_by->toBe($marker->id)
        ->and($attempt->fresh())->status->toBe('fully_marked')->and((float) $attempt->fresh()->raw_mark)->toBe(9.5);

    expect(fn () => $component->call('begin', $objective->id))->toThrow(ModelNotFoundException::class);
});

it('cannot mark a response from a test other than the one chosen', function (): void {
    $f = cbtAdminFixture();
    $marker = cbtAdminUser($f, 'cbt.mark');
    $essay = QuestionBankItem::factory()->create(['school_id' => $f['school']->id, 'subject_id' => $f['subject']->id, 'item_type' => 'essay', 'is_auto_markable' => false, 'max_mark' => 10]);
    $testA = cbtAdminTest($f, [$essay->id]);
    $testB = cbtAdminTest($f, [$essay->id]);
    $attemptB = CbtCandidateAttempt::factory()->create(['school_id' => $f['school']->id, 'test_id' => $testB->id, 'student_id' => Student::factory()->for($f['school'])->create()->id, 'status' => 'manual_marking_pending']);
    $responseB = CbtResponse::factory()->create(['school_id' => $f['school']->id, 'attempt_id' => $attemptB->id, 'question_id' => $essay->id]);

    $component = Livewire::actingAs($marker)->test(ManualMarking::class, ['school' => $f['school']])->set('testId', $testA->id);

    expect(fn () => $component->call('begin', $responseB->id))->toThrow(ModelNotFoundException::class);
});

it('closing a test submits attempts still in flight, and item analysis only runs once it has closed (BR-ACA-09-009)', function (): void {
    $f = cbtAdminFixture();
    $builder = cbtAdminUser($f, 'cbt.test.manage', 'cbt.bank.manage');
    $question = cbtAdminMcq($f);
    $test = cbtAdminTest($f, [$question->id]);

    foreach (range(1, 4) as $i) {
        $student = Student::factory()->for($f['school'])->create();
        $attempt = app(StartAttemptAction::class)->execute(new StartAttemptData($test->id, $student->id));
        app(SaveResponseAction::class)->execute(new SaveResponseData($attempt->id, $question->id, [$i % 2 === 0 ? 0 : 1]));
    }

    $analysis = Livewire::actingAs($builder)->test(ItemAnalysis::class, ['school' => $f['school']])->set('testId', $test->id)->call('compute');
    expect($question->fresh()->difficulty_index)->toBeNull();

    Livewire::actingAs($builder)->test(Builder::class, ['school' => $f['school']])->call('close', $test->id);

    expect($test->fresh()->status)->toBe('closed')->and(CbtCandidateAttempt::where('test_id', $test->id)->where('status', 'auto_marked')->count())->toBe(4)
        ->and(CbtCandidateAttempt::where('test_id', $test->id)->where('auto_submitted', true)->count())->toBe(4);

    $analysis->call('compute');
    expect($question->fresh()->difficulty_index)->not->toBeNull();
});

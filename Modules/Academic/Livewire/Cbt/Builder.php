<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Cbt;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\CloseCbtTestAction;
use Modules\Academic\Domain\Actions\CreateCbtTestAction;
use Modules\Academic\Domain\Actions\PublishCbtResultsAction;
use Modules\Academic\Domain\Actions\ScheduleCbtTestAction;
use Modules\Academic\Domain\DataObjects\CloseCbtTestData;
use Modules\Academic\Domain\DataObjects\CreateCbtTestData;
use Modules\Academic\Domain\DataObjects\PublishCbtResultsData;
use Modules\Academic\Domain\DataObjects\ScheduleCbtTestData;
use Modules\Academic\Models\AssessmentType;
use Modules\Academic\Models\CbtCandidateAttempt;
use Modules\Academic\Models\CbtTest;
use Modules\Academic\Models\QuestionBankItem;
use Modules\Academic\Models\Subject;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;

/**
 * `Academic\Cbt\Builder` (Book K ACA-09 §5, `cbt.test.manage`). Builds a
 * test by hand-picking questions or by rule (a count and a difficulty mix
 * drawn from the bank — it fails loudly if the bank is short rather than
 * repeating a question, BR-ACA-09-005) with a live view of what the bank
 * holds. Once scheduled the question set is locked (BR-ACA-09-011), closing
 * ends any attempt still in flight, and results are only published when
 * every written response has been marked (BR-ACA-09-008).
 */
#[Title('CBT tests')]
#[Layout('layouts.app')]
final class Builder extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $title = '';

    public ?int $subjectId = null;

    public ?int $termId = null;

    public string $method = 'manual';

    /** @var array<int, int> */
    public array $questionIds = [];

    public string $count = '20';

    public string $easyPercent = '30';

    public string $mediumPercent = '50';

    public string $hardPercent = '20';

    public string $topics = '';

    public string $duration = '60';

    public string $opensAt = '';

    public string $closesAt = '';

    public bool $randomiseQuestions = true;

    public bool $randomiseOptions = true;

    public bool $focusMonitoring = false;

    public string $maxTabSwitches = '3';

    public ?int $assessmentTypeId = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('cbt.test.manage');

        $this->termId = Term::query()->where('starts_on', '<=', now())->orderByDesc('starts_on')->value('id');
    }

    public function create(): void
    {
        $this->authorizePermission('cbt.test.manage');
        $this->resetErrorBag();

        $this->validate([
            'title' => ['required', 'string', 'max:200'], 'subjectId' => ['required', 'integer'], 'termId' => ['required', 'integer'],
            'duration' => ['required', 'integer', 'between:1,600'], 'opensAt' => ['required', 'date'], 'closesAt' => ['required', 'date'],
            'maxTabSwitches' => ['nullable', 'integer', 'min:0'],
        ]);

        $rules = null;

        if ($this->method === 'rule_based') {
            $mix = ['easy' => (float) $this->easyPercent / 100, 'medium' => (float) $this->mediumPercent / 100, 'hard' => (float) $this->hardPercent / 100];

            if (abs(array_sum($mix) - 1.0) > 0.001) {
                $this->addError('easyPercent', __('The difficulty mix must add up to 100%.'));

                return;
            }

            $topics = array_values(array_filter(array_map('trim', explode(',', $this->topics))));
            $rules = ['count' => (int) $this->count, 'mix' => $mix] + ($topics === [] ? [] : ['topics' => $topics]);
        }

        try {
            app(CreateCbtTestAction::class)->execute(new CreateCbtTestData(
                schoolId: $this->school->id, termId: (int) $this->termId, title: $this->title, subjectId: (int) $this->subjectId,
                durationMinutes: (int) $this->duration, opensAt: Carbon::parse($this->opensAt), closesAt: Carbon::parse($this->closesAt),
                createdByUserId: (int) auth()->id(), assemblyMethod: $this->method, assemblyRules: $rules,
                questionIds: $this->method === 'manual' ? array_map('intval', $this->questionIds) : null,
                randomiseQuestionOrder: $this->randomiseQuestions, randomiseOptionOrder: $this->randomiseOptions,
                assessmentTypeId: $this->assessmentTypeId, browserFocusMonitoring: $this->focusMonitoring,
                maxTabSwitches: $this->focusMonitoring && $this->maxTabSwitches !== '' ? (int) $this->maxTabSwitches : null,
            ));
        } catch (InvalidArgumentException|DomainException $exception) {
            $this->addError('title', $exception->getMessage());

            return;
        }

        $this->reset('title', 'questionIds');
        $this->toast(__('Draft test created.'));
    }

    public function schedule(int $testId): void
    {
        $this->move($testId, fn (CbtTest $test) => app(ScheduleCbtTestAction::class)->execute(new ScheduleCbtTestData($test->id)), __('Scheduled — its questions are now locked.'));
    }

    public function close(int $testId): void
    {
        $this->move($testId, fn (CbtTest $test) => app(CloseCbtTestAction::class)->execute(new CloseCbtTestData($test->id)), __('Closed; any attempt still in progress was submitted with what it had saved.'));
    }

    public function publish(int $testId): void
    {
        $this->move($testId, fn (CbtTest $test) => app(PublishCbtResultsAction::class)->execute(new PublishCbtResultsData($test->id, (int) auth()->id())), __('Results released.'));
    }

    /**
     * @param  callable(CbtTest): mixed  $action
     */
    private function move(int $testId, callable $action, string $message): void
    {
        $this->authorizePermission('cbt.test.manage');

        $test = CbtTest::query()->findOrFail($testId);

        try {
            $action($test);
        } catch (InvalidArgumentException|DomainException $exception) {
            $this->toast($exception->getMessage(), 'danger');

            return;
        }

        $this->toast($message);
    }

    public function render(): View
    {
        $tests = CbtTest::query()->orderByDesc('id')->limit(100)->get();
        $availability = $this->subjectId === null ? [] : QuestionBankItem::query()->where('subject_id', $this->subjectId)->where('is_active', true)
            ->selectRaw('difficulty, count(*) as total')->groupBy('difficulty')->toBase()->get()->mapWithKeys(fn (object $row): array => [(string) $row->difficulty => (int) $row->total])->all();

        return view('academic::cbt.builder', [
            'tests' => $tests,
            'attemptCounts' => CbtCandidateAttempt::query()->whereIn('test_id', $tests->pluck('id'))->selectRaw('test_id, count(*) as total')->groupBy('test_id')->toBase()->get()->mapWithKeys(fn (object $row): array => [(int) $row->test_id => (int) $row->total]),
            'subjects' => Subject::query()->orderBy('name')->get(['id', 'name']),
            'subjectNames' => Subject::query()->whereIn('id', $tests->pluck('subject_id'))->pluck('name', 'id'),
            'terms' => Term::query()->orderByDesc('starts_on')->limit(8)->get(['id', 'name']),
            'assessmentTypes' => AssessmentType::query()->orderBy('name')->get(['id', 'name']),
            'bank' => $this->subjectId === null || $this->method !== 'manual' ? collect() : QuestionBankItem::query()->where('subject_id', $this->subjectId)->where('is_active', true)->orderBy('topic')->orderBy('id')->limit(300)->get(['id', 'topic', 'difficulty', 'item_type', 'prompt', 'max_mark']),
            'availability' => $availability,
        ]);
    }
}

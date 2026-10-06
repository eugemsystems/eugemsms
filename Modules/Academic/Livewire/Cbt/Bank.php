<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Cbt;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\CreateQuestionBankItemAction;
use Modules\Academic\Domain\Actions\SetQuestionActiveAction;
use Modules\Academic\Domain\DataObjects\CreateQuestionBankItemData;
use Modules\Academic\Models\QuestionBankItem;
use Modules\Academic\Models\Subject;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Academic\Cbt\Bank` (Book K ACA-09 §5, `cbt.bank.manage`). The question
 * bank, tagged by subject, topic and difficulty, with usage and item-analysis
 * figures. Whether a question marks itself is fixed by its type: objective
 * types are auto-marked and need a correct answer, written and file-upload
 * types are marked by hand. Multiple-choice answers are the zero-based
 * positions of the right options; true/false is stored as true or false;
 * fill-in answers are the accepted text (matched exactly). Matching items
 * are not authored here. Retiring a question never alters a test already
 * built from it.
 */
#[Title('Question bank')]
#[Layout('layouts.app')]
final class Bank extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $subjectFilter = null;

    public string $difficultyFilter = '';

    public string $topicFilter = '';

    public ?int $subjectId = null;

    public string $itemType = 'mcq';

    public string $difficulty = 'medium';

    public string $topic = '';

    public string $prompt = '';

    public string $maxMark = '1';

    public string $options = '';

    public string $correct = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('cbt.bank.manage');
    }

    public function create(): void
    {
        $this->authorizePermission('cbt.bank.manage');
        $this->resetErrorBag();

        $this->validate(['subjectId' => ['required', 'integer'], 'prompt' => ['required', 'string', 'max:5000'], 'maxMark' => ['required', 'numeric', 'gt:0'], 'topic' => ['nullable', 'string', 'max:150']]);

        $optionLines = array_values(array_filter(array_map('trim', preg_split('/\R/', $this->options) ?: [])));
        $autoMarkable = in_array($this->itemType, ['mcq', 'true_false', 'matching', 'fill_in'], true);
        $correct = null;

        if ($this->itemType === 'mcq') {
            $indexes = array_values(array_filter(array_map('trim', explode(',', $this->correct)), fn (string $value): bool => $value !== ''));

            if ($indexes === [] || array_filter($indexes, fn (string $value): bool => ! ctype_digit($value) || (int) $value >= count($optionLines)) !== []) {
                $this->addError('correct', __('List the numbers of the correct options, counting the first option as 0.'));

                return;
            }

            $correct = array_map('intval', $indexes);
        } elseif ($this->itemType === 'true_false') {
            $correct = [strtolower(trim($this->correct)) === 'true'];

            if (! in_array(strtolower(trim($this->correct)), ['true', 'false'], true)) {
                $this->addError('correct', __('Enter true or false.'));

                return;
            }
        } elseif ($this->itemType === 'fill_in') {
            $correct = array_values(array_filter(array_map('trim', explode('|', $this->correct)), fn (string $value): bool => $value !== ''));
        }

        try {
            app(CreateQuestionBankItemAction::class)->execute(new CreateQuestionBankItemData(
                schoolId: $this->school->id, subjectId: (int) $this->subjectId, itemType: $this->itemType, difficulty: $this->difficulty,
                prompt: $this->prompt, maxMark: (float) $this->maxMark, isAutoMarkable: $autoMarkable, createdByUserId: (int) auth()->id(),
                topic: $this->topic === '' ? null : $this->topic,
                options: $this->itemType === 'mcq' ? $optionLines : null, correctAnswer: $correct,
            ));
        } catch (InvalidArgumentException $exception) {
            $this->addError('prompt', $exception->getMessage());

            return;
        }

        $this->reset('prompt', 'options', 'correct', 'topic');
        $this->toast(__('Question added.'));
    }

    public function setActive(int $questionId, bool $isActive): void
    {
        $this->authorizePermission('cbt.bank.manage');

        app(SetQuestionActiveAction::class)->execute(QuestionBankItem::query()->findOrFail($questionId)->id, $isActive);

        $this->toast($isActive ? __('Question restored.') : __('Question retired from new tests.'));
    }

    public function render(): View
    {
        $like = '%'.addcslashes(trim($this->topicFilter), '%_\\').'%';

        return view('academic::cbt.bank', [
            'questions' => QuestionBankItem::query()
                ->when($this->subjectFilter !== null, fn ($q) => $q->where('subject_id', $this->subjectFilter))
                ->when(in_array($this->difficultyFilter, ['easy', 'medium', 'hard'], true), fn ($q) => $q->where('difficulty', $this->difficultyFilter))
                ->when(trim($this->topicFilter) !== '', fn ($q) => $q->where('topic', 'like', $like))
                ->orderByDesc('id')->limit(200)->get(),
            'subjects' => Subject::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}

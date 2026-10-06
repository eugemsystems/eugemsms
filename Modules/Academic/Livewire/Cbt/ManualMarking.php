<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Cbt;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\MarkCbtResponseAction;
use Modules\Academic\Domain\DataObjects\MarkCbtResponseData;
use Modules\Academic\Models\CbtCandidateAttempt;
use Modules\Academic\Models\CbtResponse;
use Modules\Academic\Models\CbtTest;
use Modules\Academic\Models\QuestionBankItem;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\Student;

/**
 * `Academic\Cbt\ManualMarking` (Book K ACA-09 §5, `cbt.mark`). The queue of
 * written responses awaiting a mark. Auto-marked items never appear here
 * and cannot be re-marked by hand (BR-ACA-09-007). A mark is bounded by the
 * question's maximum and the response must belong to a submitted attempt of
 * the chosen test; the attempt's total and status are recomputed by the
 * Action.
 */
#[Title('Manual marking')]
#[Layout('layouts.app')]
final class ManualMarking extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $testId = null;

    public ?int $markingId = null;

    public string $mark = '';

    public string $feedback = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('cbt.mark');

        $this->testId = CbtTest::query()->whereIn('status', ['closed', 'open'])->orderByDesc('id')->value('id') ?? CbtTest::query()->orderByDesc('id')->value('id');
    }

    public function begin(int $responseId): void
    {
        $response = $this->response($responseId);

        $this->markingId = $response->id;
        $this->mark = $response->mark_awarded === null ? '' : (string) $response->mark_awarded;
        $this->feedback = (string) $response->manual_feedback;
        $this->resetErrorBag();
    }

    public function save(): void
    {
        $this->authorizePermission('cbt.mark');
        $this->resetErrorBag();

        abort_unless($this->markingId !== null, 422);
        $response = $this->response($this->markingId);

        $this->validate(['mark' => ['required', 'numeric', 'min:0'], 'feedback' => ['nullable', 'string', 'max:5000']]);

        try {
            app(MarkCbtResponseAction::class)->execute(new MarkCbtResponseData($response->id, (float) $this->mark, (int) auth()->id(), $this->feedback === '' ? null : $this->feedback));
        } catch (InvalidArgumentException|DomainException $exception) {
            $this->addError('mark', $exception->getMessage());

            return;
        }

        $this->markingId = null;
        $this->toast(__('Mark saved.'));
    }

    private function response(int $responseId): CbtResponse
    {
        $this->authorizePermission('cbt.mark');

        $writtenIds = QuestionBankItem::query()->where('is_auto_markable', false)->whereIn('id', CbtTest::query()->findOrFail($this->testId)->question_ids ?? [])->pluck('id');

        return CbtResponse::query()
            ->whereIn('attempt_id', CbtCandidateAttempt::query()->where('test_id', $this->testId)->select('id'))
            ->whereIn('question_id', $writtenIds)
            ->findOrFail($responseId);
    }

    public function render(): View
    {
        $written = $this->testId === null ? collect() : QuestionBankItem::query()->where('is_auto_markable', false)->whereIn('id', CbtTest::query()->findOrFail($this->testId)->question_ids ?? [])->get()->keyBy('id');
        $responses = $this->testId === null ? collect() : CbtResponse::query()
            ->whereIn('attempt_id', CbtCandidateAttempt::query()->where('test_id', $this->testId)->whereIn('status', ['manual_marking_pending', 'fully_marked'])->select('id'))
            ->whereIn('question_id', $written->keys())
            ->orderByRaw('mark_awarded is not null')->orderBy('id')->limit(200)->get();
        $attempts = CbtCandidateAttempt::query()->whereIn('id', $responses->pluck('attempt_id'))->get()->keyBy('id');

        return view('academic::cbt.marking', [
            'tests' => CbtTest::query()->orderByDesc('id')->limit(30)->get(['id', 'title', 'status']),
            'responses' => $responses,
            'questions' => $written,
            'attempts' => $attempts,
            'students' => Student::query()->whereIn('id', $attempts->pluck('student_id'))->get()->keyBy('id'),
        ]);
    }
}

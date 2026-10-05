<?php

declare(strict_types=1);

namespace Modules\Comms\Livewire\Surveys;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Comms\Domain\Actions\CloseSurveyAction;
use Modules\Comms\Domain\Actions\CreateSurveyAction;
use Modules\Comms\Livewire\Concerns\ResolvesAudienceScopes;
use Modules\Comms\Models\Survey;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Comms\Surveys\Builder` (Book I COM-08 §4, `surveys.manage`). A survey
 * is created already open — the backend has no draft step — and cannot
 * be edited afterwards (no update Action exists), so the builder says so
 * before saving. Question order is the row order; a skip rule may only
 * jump *forward* to a later question (or past the end), which is also
 * all `SkipLogicEvaluator` can follow, so a loop is impossible. An
 * anonymous survey stores no respondent identity at all (BR-COM-08-001).
 * Distribution to an audience and the respondent-facing form are portal
 * surfaces and are not built here.
 */
#[Title('Survey builder')]
#[Layout('layouts.app')]
final class Builder extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use ResolvesAudienceScopes;
    use Toasts;

    /** @var array<int, string> */
    public const array QUESTION_TYPES = ['single_choice', 'multi_choice', 'scale', 'text', 'nps'];

    public string $title = '';

    public string $purpose = 'satisfaction';

    public string $audienceScope = 'whole_school';

    public bool $isAnonymous = false;

    /**
     * @var array<int, array{type: string, prompt: string, options: string, required: bool, skipIf: string, skipTo: string}>
     */
    public array $questions = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('surveys.manage');

        $this->addQuestion();
    }

    public function addQuestion(): void
    {
        $this->questions[] = ['type' => 'single_choice', 'prompt' => '', 'options' => '', 'required' => true, 'skipIf' => '', 'skipTo' => ''];
    }

    public function removeQuestion(int $index): void
    {
        unset($this->questions[$index]);
        $this->questions = array_values($this->questions);
    }

    public function save(): void
    {
        $this->authorizePermission('surveys.manage');

        $this->validate([
            'title' => ['required', 'string', 'max:200'],
            'purpose' => ['required', 'in:satisfaction,feedback,research,exit_interview'],
            'audienceScope' => ['required', 'in:'.implode(',', array_keys($this->audienceScopeOptions()))],
            'questions' => ['required', 'array', 'min:1', 'max:50'],
            'questions.*.type' => ['required', 'in:'.implode(',', self::QUESTION_TYPES)],
            'questions.*.prompt' => ['required', 'string', 'max:500'],
            'questions.*.skipTo' => ['nullable', 'integer', 'min:2'],
        ]);

        $count = count($this->questions);
        $payload = [];

        foreach (array_values($this->questions) as $index => $question) {
            $sequence = $index + 1;
            $options = array_values(array_filter(array_map('trim', explode("\n", $question['options'])), fn (string $option): bool => $option !== ''));

            if (in_array($question['type'], ['single_choice', 'multi_choice'], true) && count($options) < 2) {
                $this->addError("questions.{$index}.options", __('A choice question needs at least two options, one per line.'));

                return;
            }

            $skipLogic = null;

            if ($question['skipTo'] !== '' && $question['skipIf'] !== '') {
                if ((int) $question['skipTo'] <= $sequence || (int) $question['skipTo'] > $count + 1) {
                    $this->addError("questions.{$index}.skipTo", __('A skip rule must jump forward to a later question.'));

                    return;
                }

                $skipLogic = ['if_answer' => $question['skipIf'], 'go_to_sequence' => (int) $question['skipTo']];
            }

            $payload[] = [
                'sequence' => $sequence,
                'questionType' => $question['type'],
                'prompt' => $question['prompt'],
                'options' => in_array($question['type'], ['single_choice', 'multi_choice'], true) ? $options : null,
                'isRequired' => (bool) $question['required'],
                'skipLogic' => $skipLogic,
            ];
        }

        app(CreateSurveyAction::class)->execute(
            schoolId: $this->school->id,
            title: $this->title,
            purpose: $this->purpose,
            audienceScope: $this->audienceScope,
            isAnonymous: $this->isAnonymous,
            questions: $payload,
        );

        $this->reset(['title', 'questions', 'isAnonymous']);
        $this->addQuestion();
        $this->toast(__('Survey created and open.'));
    }

    public function close(int $surveyId): void
    {
        $this->authorizePermission('surveys.manage');

        $survey = Survey::where('school_id', $this->school->id)->findOrFail($surveyId);
        app(CloseSurveyAction::class)->execute($survey->id);

        $this->toast(__('Survey closed. Responses are kept.'));
    }

    public function render(): View
    {
        return view('comms::surveys.builder', [
            'surveys' => Survey::where('school_id', $this->school->id)->orderByDesc('id')->limit(50)->get(['id', 'title', 'purpose', 'is_anonymous', 'status', 'response_count']),
            'scopeOptions' => $this->audienceScopeOptions(),
            'questionTypes' => self::QUESTION_TYPES,
        ]);
    }
}

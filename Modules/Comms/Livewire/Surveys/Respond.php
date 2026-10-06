<?php

declare(strict_types=1);

namespace Modules\Comms\Livewire\Surveys;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Comms\Domain\Actions\SubmitSurveyResponseAction;
use Modules\Comms\Domain\DataObjects\SubmitSurveyResponseData;
use Modules\Comms\Models\Survey;
use Modules\Comms\Models\SurveyResponse;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\Guardian;
use Modules\People\Models\Staff;

/**
 * `Surveys\Respond` (Book I COM-08 §3 ⭐, BR-COM-08-001/002). The respondent-facing
 * form: any member of the school sees the open surveys aimed at them and answers
 * once. Identity is derived server-side from the signed-in user's staff or
 * guardian record and is dropped by the Action for an anonymous survey — the
 * form never asks who the respondent is. A "staff" survey is for staff only, and
 * a survey that is not anonymous needs a respondent the school knows, so the
 * answer can be counted once. Questions a previous answer made irrelevant are
 * hidden here and dropped again by the Action, so a tampered request cannot
 * smuggle one in.
 */
#[Title('Surveys')]
#[Layout('layouts.app')]
final class Respond extends Component
{
    use InteractsWithSchool;
    use Toasts;

    public ?int $surveyId = null;

    /** @var array<int, mixed> sequence => answer (a string, or a list for multiple choice) */
    public array $answers = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
    }

    public function open(int $id): void
    {
        $survey = $this->answerableSurveys()->firstWhere('id', $id);

        $this->surveyId = $survey?->id;
        $this->answers = [];
    }

    public function submit(): void
    {
        $survey = $this->surveyId !== null ? $this->answerableSurveys()->firstWhere('id', $this->surveyId) : null;

        if ($survey === null) {
            $this->toast(__('That survey is not available to you.'), 'danger');
            $this->surveyId = null;

            return;
        }

        [$type, $id] = $this->respondent();

        try {
            app(SubmitSurveyResponseAction::class)->execute(new SubmitSurveyResponseData(
                surveyId: $survey->id,
                answersBySequence: $this->answers,
                respondentType: $type,
                respondentId: $id,
            ));
        } catch (InvalidArgumentException|InvalidStateTransitionException $exception) {
            $this->toast($exception->getMessage(), 'danger');

            return;
        }

        $this->reset('surveyId', 'answers');
        $this->toast(__('Thank you — your response was recorded.'));
    }

    /**
     * Sequences a respondent should currently see, given the answers so far.
     *
     * @return array<int, true>
     */
    private function visibleSequences(Survey $survey): array
    {
        $visible = [];
        $skipUntil = null;

        foreach ($survey->questions as $question) {
            if ($skipUntil !== null && $question->sequence < $skipUntil) {
                continue;
            }

            $skipUntil = null;
            $visible[$question->sequence] = true;
            $skip = $question->skip_logic;
            $answer = $this->answers[$question->sequence] ?? null;

            if ($skip !== null && is_scalar($answer) && (string) $answer === (string) $skip['if_answer']) {
                $skipUntil = (int) $skip['go_to_sequence'];
            }
        }

        return $visible;
    }

    /**
     * @return array{0: string|null, 1: int|null}
     */
    private function respondent(): array
    {
        $userId = (int) auth()->id();
        $staff = Staff::where('school_id', $this->school->id)->where('user_id', $userId)->first();

        if ($staff !== null) {
            return ['staff', $staff->id];
        }

        $guardian = Guardian::where('school_id', $this->school->id)->where('user_id', $userId)->first();

        return $guardian !== null ? ['guardian', $guardian->id] : [null, null];
    }

    /**
     * Open surveys aimed at this user that they have not already answered. An
     * anonymous survey cannot be matched to a respondent, so it stays listed.
     *
     * @return Collection<int, Survey>
     */
    private function answerableSurveys(): Collection
    {
        [$type, $id] = $this->respondent();
        $now = now();

        return Survey::query()
            ->where('school_id', $this->school->id)
            ->where('status', 'open')
            ->where(fn ($q) => $q->whereNull('opens_at')->orWhere('opens_at', '<=', $now))
            ->where(fn ($q) => $q->whereNull('closes_at')->orWhere('closes_at', '>=', $now))
            ->with('questions')
            ->orderByDesc('id')
            ->get()
            ->filter(function (Survey $survey) use ($type, $id): bool {
                if ($survey->audience_scope === 'staff' && $type !== 'staff') {
                    return false;
                }

                if ($survey->is_anonymous) {
                    return true;
                }

                if ($type === null || $id === null) {
                    return false;
                }

                return ! SurveyResponse::where('survey_id', $survey->id)->where('respondent_type', $type)->where('respondent_id', $id)->exists();
            })
            ->values();
    }

    public function render(): View
    {
        $surveys = $this->answerableSurveys();
        $survey = $this->surveyId !== null ? $surveys->firstWhere('id', $this->surveyId) : null;

        return view('comms::surveys.respond', [
            'surveys' => $surveys,
            'survey' => $survey,
            'visible' => $survey !== null ? $this->visibleSequences($survey) : [],
        ]);
    }
}

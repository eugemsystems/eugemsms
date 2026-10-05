<?php

declare(strict_types=1);

namespace Modules\Comms\Livewire\Surveys;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Comms\Models\Survey;
use Modules\Comms\Models\SurveyQuestion;
use Modules\Comms\Models\SurveyResponseAnswer;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Comms\Surveys\Results` (Book I COM-08 §4, `surveys.view`).
 * Aggregates only: counts per option, the mean of a scale, the NPS
 * (promoters 9–10 minus detractors 0–6), and free-text answers listed
 * with no respondent attached — this screen never selects a respondent
 * type or id, whether or not the survey was anonymous, so it cannot
 * leak one. Free text is capped at 50 per question.
 */
#[Title('Survey results')]
#[Layout('layouts.app')]
final class Results extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public ?int $surveyId = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('surveys.view');
    }

    public function render(): View
    {
        $surveys = Survey::where('school_id', $this->school->id)->orderByDesc('id')->limit(100)->get(['id', 'title', 'status', 'is_anonymous', 'response_count']);
        $survey = $this->surveyId !== null ? $surveys->firstWhere('id', $this->surveyId) : null;

        return view('comms::surveys.results', [
            'surveys' => $surveys,
            'survey' => $survey,
            'summaries' => $survey !== null ? $this->summarise($survey) : [],
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function summarise(Survey $survey): array
    {
        $questions = SurveyQuestion::where('survey_id', $survey->id)->orderBy('sequence')->get();
        $answers = SurveyResponseAnswer::whereIn('question_id', $questions->pluck('id'))->get(['question_id', 'answer_value'])->groupBy('question_id');

        return $questions->map(function (SurveyQuestion $question) use ($answers): array {
            $values = ($answers->get($question->id) ?? collect())->pluck('answer_value');
            $summary = ['sequence' => $question->sequence, 'prompt' => $question->prompt, 'type' => $question->question_type, 'answered' => $values->count()];

            switch ($question->question_type) {
                case 'single_choice':
                case 'multi_choice':
                    $counts = array_fill_keys($question->options ?? [], 0);
                    foreach ($values as $value) {
                        foreach ((array) $value as $choice) {
                            $counts[(string) $choice] = ($counts[(string) $choice] ?? 0) + 1;
                        }
                    }
                    $summary['counts'] = $counts;
                    break;
                case 'scale':
                    $numbers = $values->filter(fn ($value): bool => is_numeric($value))->map(fn ($value): float => (float) $value);
                    $summary['average'] = $numbers->isNotEmpty() ? round($numbers->avg(), 2) : null;
                    break;
                case 'nps':
                    $numbers = $values->filter(fn ($value): bool => is_numeric($value))->map(fn ($value): int => (int) $value);
                    $total = $numbers->count();
                    $promoters = $numbers->filter(fn (int $n): bool => $n >= 9)->count();
                    $detractors = $numbers->filter(fn (int $n): bool => $n <= 6)->count();
                    $summary['nps'] = $total > 0 ? (int) round(($promoters - $detractors) / $total * 100) : null;
                    break;
                default:
                    $summary['texts'] = $values->filter(fn ($value): bool => is_string($value) && $value !== '')->take(50)->values()->all();
            }

            return $summary;
        })->all();
    }
}

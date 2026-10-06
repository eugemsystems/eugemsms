<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Modules\Comms\Domain\DataObjects\SubmitSurveyResponseData;
use Modules\Comms\Domain\Support\SkipLogicEvaluator;
use Modules\Comms\Models\Survey;
use Modules\Comms\Models\SurveyResponse;
use Modules\Comms\Models\SurveyResponseAnswer;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * ACT-SubmitSurveyResponse (Book I COM-08 §3 ⭐/BR-COM-08-001/002
 * (AC-COM-08-001)). `respondent_type`/`respondent_id` are forced to
 * `null` for an anonymous survey UNCONDITIONALLY — even if the caller
 * (a bug, a tampered request) supplies them, they never reach the
 * row. `SkipLogicEvaluator` drops any answer for a question the
 * respondent's own prior answers made unreachable, before anything
 * is persisted.
 */
final class SubmitSurveyResponseAction extends Action
{
    public function __construct(
        private readonly SkipLogicEvaluator $skipLogic,
    ) {}

    public function execute(SubmitSurveyResponseData $data): SurveyResponse
    {
        $survey = Survey::with('questions')->findOrFail($data->surveyId);
        $this->assertOpen($survey);

        $activeAnswers = $this->skipLogic->filterActiveAnswers($survey->questions, $data->answersBySequence);
        $this->assertAnswersValid($survey, $activeAnswers);

        if (! $survey->is_anonymous && $data->respondentType !== null && $data->respondentId !== null
            && SurveyResponse::where('survey_id', $survey->id)->where('respondent_type', $data->respondentType)->where('respondent_id', $data->respondentId)->exists()) {
            throw new InvalidStateTransitionException('This survey has already been answered by this respondent.');
        }

        return $this->transaction(function () use ($survey, $data, $activeAnswers): SurveyResponse {
            $response = SurveyResponse::create([
                'school_id' => $survey->school_id,
                'survey_id' => $survey->id,
                'respondent_type' => $survey->is_anonymous ? null : $data->respondentType,
                'respondent_id' => $survey->is_anonymous ? null : $data->respondentId,
                'submitted_at' => Carbon::now(),
            ]);

            $questionsBySequence = $survey->questions->keyBy('sequence');

            foreach ($activeAnswers as $sequence => $value) {
                SurveyResponseAnswer::create([
                    'response_id' => $response->id,
                    'question_id' => $questionsBySequence[$sequence]->id,
                    'answer_value' => $value,
                ]);
            }

            $survey->increment('response_count');

            return $response;
        });
    }

    private function assertOpen(Survey $survey): void
    {
        $now = Carbon::now();

        if ($survey->status !== 'open'
            || ($survey->opens_at !== null && $survey->opens_at->isFuture())
            || ($survey->closes_at !== null && $survey->closes_at->lessThan($now))) {
            throw new InvalidStateTransitionException('This survey is not open for responses.');
        }
    }

    /**
     * Only reachable questions are checked (a skipped question's answer was already dropped):
     * a required question needs an answer, a choice must be one of the options, a scale is
     * 1–5, NPS is 0–10 and free text is capped.
     *
     * @param  array<int, mixed>  $activeAnswers
     */
    private function assertAnswersValid(Survey $survey, array $activeAnswers): void
    {
        $reachable = $this->reachableSequences($survey, $activeAnswers);

        foreach ($survey->questions as $question) {
            $answer = $activeAnswers[$question->sequence] ?? null;
            $isAnswered = $answer !== null && $answer !== '' && $answer !== [];

            if (! $isAnswered) {
                if ($question->is_required && isset($reachable[$question->sequence])) {
                    throw new InvalidArgumentException("Question {$question->sequence} needs an answer.");
                }

                continue;
            }

            $valid = match ($question->question_type) {
                'single_choice' => is_string($answer) && in_array($answer, $question->options ?? [], true),
                'multi_choice' => is_array($answer) && array_diff($answer, $question->options ?? []) === [],
                'scale' => is_numeric($answer) && (int) $answer >= 1 && (int) $answer <= 5,
                'nps' => is_numeric($answer) && (int) $answer >= 0 && (int) $answer <= 10,
                'text' => is_string($answer) && mb_strlen($answer) <= 5000,
                default => true,
            };

            if (! $valid) {
                throw new InvalidArgumentException("Question {$question->sequence} has an invalid answer.");
            }
        }
    }

    /**
     * @param  array<int, mixed>  $activeAnswers
     * @return array<int, true> sequences a respondent could actually reach given their answers
     */
    private function reachableSequences(Survey $survey, array $activeAnswers): array
    {
        $reachable = [];
        $skipUntil = null;

        foreach ($survey->questions as $question) {
            if ($skipUntil !== null && $question->sequence < $skipUntil) {
                continue;
            }

            $skipUntil = null;
            $reachable[$question->sequence] = true;
            $skip = $question->skip_logic;

            if ($skip !== null && array_key_exists($question->sequence, $activeAnswers) && (string) $activeAnswers[$question->sequence] === (string) $skip['if_answer']) {
                $skipUntil = (int) $skip['go_to_sequence'];
            }
        }

        return $reachable;
    }
}

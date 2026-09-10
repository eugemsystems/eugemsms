<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Comms\Domain\DataObjects\SubmitSurveyResponseData;
use Modules\Comms\Domain\Support\SkipLogicEvaluator;
use Modules\Comms\Models\Survey;
use Modules\Comms\Models\SurveyResponse;
use Modules\Comms\Models\SurveyResponseAnswer;
use Modules\Core\Domain\Actions\Action;

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
        $activeAnswers = $this->skipLogic->filterActiveAnswers($survey->questions, $data->answersBySequence);

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
}

<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Modules\Comms\Models\Survey;
use Modules\Comms\Models\SurveyQuestion;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-CreateSurvey (Book I COM-08 §2).
 */
final class CreateSurveyAction extends Action
{
    /**
     * @param  array<int, array{sequence: int, questionType: string, prompt: string, options?: array<int, mixed>|null, isRequired?: bool, skipLogic?: array{if_answer: mixed, go_to_sequence: int}|null}>  $questions
     */
    public function execute(
        int $schoolId,
        string $title,
        string $purpose,
        string $audienceScope,
        bool $isAnonymous,
        array $questions,
    ): Survey {
        return $this->transaction(function () use ($schoolId, $title, $purpose, $audienceScope, $isAnonymous, $questions): Survey {
            $survey = Survey::create([
                'school_id' => $schoolId,
                'title' => $title,
                'purpose' => $purpose,
                'audience_scope' => $audienceScope,
                'is_anonymous' => $isAnonymous,
                'status' => 'open',
            ]);

            foreach ($questions as $question) {
                SurveyQuestion::create([
                    'survey_id' => $survey->id,
                    'sequence' => $question['sequence'],
                    'question_type' => $question['questionType'],
                    'prompt' => $question['prompt'],
                    'options' => $question['options'] ?? null,
                    'is_required' => $question['isRequired'] ?? true,
                    'skip_logic' => $question['skipLogic'] ?? null,
                ]);
            }

            return $survey;
        });
    }
}

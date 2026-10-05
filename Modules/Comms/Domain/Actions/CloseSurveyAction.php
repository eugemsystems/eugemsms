<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Modules\Comms\Models\Survey;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-CloseSurvey (Book I COM-08 §2). `CreateSurveyAction` opens a
 * survey immediately and the backend had no way to close one. Closing
 * is idempotent and keeps every response.
 */
final class CloseSurveyAction extends Action
{
    public function execute(int $surveyId): Survey
    {
        return $this->transaction(function () use ($surveyId): Survey {
            $survey = Survey::findOrFail($surveyId);

            if ($survey->status !== 'closed') {
                $survey->update(['status' => 'closed', 'closes_at' => now()]);
            }

            return $survey;
        });
    }
}

<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\People\Domain\DataObjects\RecordInterviewOutcomeData;
use Modules\People\Models\Application;
use Modules\People\Models\Interview;

/**
 * ACT-RecordInterviewOutcome (Book C PPL-02 §2). The panel's scores and
 * recommendation. A candidate who did not attend has none. Completing it moves
 * the application to `interview_completed` if it had not gone further.
 */
final class RecordInterviewOutcomeAction extends Action
{
    public const RECOMMENDATIONS = ['accept', 'waitlist', 'decline'];

    public function execute(RecordInterviewOutcomeData $data): Interview
    {
        $interview = Interview::findOrFail($data->interviewId);

        if ($interview->completed_at !== null) {
            throw new InvalidArgumentException('This interview has already been recorded.');
        }

        if ($data->attended && ($data->scores === [] || ! in_array((string) $data->recommendation, self::RECOMMENDATIONS, true))) {
            throw new InvalidArgumentException('Score the interview and give a recommendation (accept, waitlist or decline).');
        }

        foreach ($data->scores as $criterion => $score) {
            if ((float) $score < 0 || (float) $score > 100) {
                throw new InvalidArgumentException("The score for {$criterion} must be between 0 and 100.");
            }
        }

        return $this->transaction(function () use ($interview, $data): Interview {
            $interview->update([
                'attended' => $data->attended,
                'scores' => $data->attended ? $data->scores : null,
                'total_score' => $data->attended ? round((float) array_sum($data->scores), 2) : null,
                'recommendation' => $data->attended ? $data->recommendation : null,
                'panel_notes' => $data->panelNotes,
                'completed_at' => Carbon::now()->toDateTimeString(),
            ]);

            Application::query()->whereKey($interview->application_id)->whereIn('status', ['submitted', 'under_review', 'exam_completed'])->update(['status' => 'interview_completed']);

            return $interview->fresh();
        });
    }
}

<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Academic\Domain\DataObjects\SubmitAssessmentMarksData;
use Modules\Academic\Models\Assessment;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * ACT-SubmitAssessmentMarks (Book D ACA-05 §5/§6/BR-ACA-05-006). The
 * line marks cross from "only the entering teacher can see this" to
 * visible more broadly, and from freely-overwritable to
 * amendment-only.
 */
final class SubmitAssessmentMarksAction extends Action
{
    public function execute(SubmitAssessmentMarksData $data): Assessment
    {
        $assessment = Assessment::findOrFail($data->assessmentId);

        if (! in_array($assessment->status, ['draft', 'open'], true)) {
            throw new InvalidStateTransitionException(
                "An assessment in [{$assessment->status}] cannot be submitted.",
                ['assessment_id' => $assessment->id, 'status' => $assessment->status],
            );
        }

        return $this->transaction(function () use ($assessment, $data): Assessment {
            $assessment->update([
                'status' => 'submitted',
                'submitted_by' => $data->submittedByUserId,
                'submitted_at' => Carbon::now(),
            ]);

            return $assessment->fresh();
        });
    }
}

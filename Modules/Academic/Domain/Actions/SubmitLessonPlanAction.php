<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\SubmitLessonPlanData;
use Modules\Academic\Domain\Exceptions\SchemeOfWorkNotApprovedException;
use Modules\Academic\Models\LessonPlan;
use Modules\Academic\Models\SchemeOfWork;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * ACT-SubmitLessonPlan (Book K ACA-11 §3/BR-ACA-11-001/AC-ACA-11-001).
 * A lesson plan linked to a scheme of work is blocked from submission
 * until that scheme is HOD-approved; an unlinked lesson plan has no
 * such gate.
 */
final class SubmitLessonPlanAction extends Action
{
    public function execute(SubmitLessonPlanData $data): LessonPlan
    {
        $plan = LessonPlan::findOrFail($data->lessonPlanId);

        if (! in_array($plan->status, ['draft'], true)) {
            throw new InvalidStateTransitionException(
                "Lesson plan #{$plan->id} in [{$plan->status}] cannot be submitted.",
                ['lesson_plan_id' => $plan->id, 'status' => $plan->status],
            );
        }

        if ($plan->scheme_of_work_id !== null) {
            $scheme = SchemeOfWork::findOrFail($plan->scheme_of_work_id);

            if ($scheme->status !== 'approved') {
                throw SchemeOfWorkNotApprovedException::forScheme($scheme->id, $scheme->status);
            }
        }

        return $this->transaction(function () use ($plan): LessonPlan {
            $plan->update(['status' => 'submitted']);

            return $plan->fresh();
        });
    }
}

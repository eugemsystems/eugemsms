<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\ReviewLessonPlanData;
use Modules\Academic\Models\LessonPlan;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

final class ReviewLessonPlanAction extends Action
{
    public function execute(ReviewLessonPlanData $data): LessonPlan
    {
        $plan = LessonPlan::findOrFail($data->lessonPlanId);

        if ($plan->status !== 'submitted') {
            throw new InvalidStateTransitionException(
                "Lesson plan #{$plan->id} in [{$plan->status}] cannot be reviewed.",
                ['lesson_plan_id' => $plan->id, 'status' => $plan->status],
            );
        }

        return $this->transaction(function () use ($plan, $data): LessonPlan {
            $plan->update(['status' => 'reviewed', 'hod_comments' => $data->hodComments]);

            return $plan->fresh();
        });
    }
}

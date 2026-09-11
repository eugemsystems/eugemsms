<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Saas\Domain\Exceptions\OnboardingStepNotFoundException;
use Modules\Saas\Models\OnboardingChecklist;

/**
 * ACT-CompleteOnboardingStep (Book J SAA-03 §2/§4/BR-SAA-03-001).
 * Reaching a fully-complete checklist flips `status` to `go_live` —
 * this is the only place that transition happens.
 */
final class CompleteOnboardingStepAction extends Action
{
    public function execute(int $checklistId, string $stepKey, ?string $owner = null): OnboardingChecklist
    {
        $checklist = OnboardingChecklist::query()->findOrFail($checklistId);

        $found = false;
        $steps = array_map(function (array $step) use ($stepKey, $owner, &$found): array {
            if ($step['key'] === $stepKey) {
                $found = true;
                $step['completed_at'] = Carbon::now()->toIso8601String();
                $step['owner'] = $owner ?? $step['owner'];
            }

            return $step;
        }, $checklist->steps);

        if (! $found) {
            throw new OnboardingStepNotFoundException("Checklist [{$checklist->id}] has no step keyed [{$stepKey}].");
        }

        return $this->transaction(function () use ($checklist, $steps): OnboardingChecklist {
            $checklist->steps = $steps;
            $allComplete = $checklist->isFullyComplete();

            $checklist->update([
                'steps' => $steps,
                'status' => $allComplete ? 'go_live' : 'in_progress',
            ]);

            return $checklist->fresh();
        });
    }
}

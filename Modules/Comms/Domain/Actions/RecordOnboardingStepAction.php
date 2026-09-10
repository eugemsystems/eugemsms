<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Comms\Domain\Events\OnboardingCompleted;
use Modules\Comms\Models\OnboardingProgress;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-RecordOnboardingStep (Book I COM-03 §6/BR-COM-03-009). A step is
 * additive — appending an already-recorded step is a no-op, never a
 * duplicate. `isFinalStep` is the caller's own knowledge of its flow
 * (this action doesn't hard-code a step list per persona), and sets
 * `completed_at` only when the caller says this was the last one.
 */
final class RecordOnboardingStepAction extends Action
{
    public function execute(int $userId, string $persona, string $step, bool $isFinalStep = false): OnboardingProgress
    {
        return $this->transaction(function () use ($userId, $persona, $step, $isFinalStep): OnboardingProgress {
            $progress = OnboardingProgress::firstOrCreate(
                ['user_id' => $userId],
                ['persona' => $persona, 'steps_completed' => []],
            );

            $steps = $progress->steps_completed;

            if (! in_array($step, $steps, true)) {
                $steps[] = $step;
            }

            $progress->update([
                'steps_completed' => $steps,
                'completed_at' => $isFinalStep ? Carbon::now() : $progress->completed_at,
            ]);

            if ($isFinalStep) {
                event(new OnboardingCompleted($progress));
            }

            return $progress;
        });
    }
}

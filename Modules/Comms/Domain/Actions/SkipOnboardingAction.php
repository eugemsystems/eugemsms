<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Comms\Models\OnboardingProgress;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-SkipOnboarding (Book I COM-03 §6/BR-COM-03-009). A skip is
 * itself recorded, but never locks the record — every step remains
 * available from settings afterward, since `RecordOnboardingStepAction`
 * doesn't check `skipped_at` at all.
 */
final class SkipOnboardingAction extends Action
{
    public function execute(int $userId, string $persona): OnboardingProgress
    {
        return $this->transaction(function () use ($userId, $persona): OnboardingProgress {
            $progress = OnboardingProgress::firstOrCreate(
                ['user_id' => $userId],
                ['persona' => $persona, 'steps_completed' => []],
            );

            $progress->update(['skipped_at' => Carbon::now()]);

            return $progress;
        });
    }
}

<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Welfare\Models\VulnerableLearnerRegistration;

/**
 * ACT-ReviewVulnerableLearner (Book G BRD-08 §2/BR-BRD-08-018 —
 * periodic review; sets the next one from the review frequency).
 */
final class ReviewVulnerableLearnerAction extends Action
{
    public function execute(int $registrationId, ?string $updatedSupportPlan = null): VulnerableLearnerRegistration
    {
        $registration = VulnerableLearnerRegistration::findOrFail($registrationId);

        return $this->transaction(fn (): VulnerableLearnerRegistration => tap($registration)->update([
            'support_plan' => $updatedSupportPlan ?? $registration->support_plan,
            'next_review_on' => now()->addDays($registration->review_frequency_days)->toDateString(),
        ]));
    }
}

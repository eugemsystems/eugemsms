<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Saas\Domain\DataObjects\StartOnboardingChecklistData;
use Modules\Saas\Models\OnboardingChecklist;

/**
 * ACT-StartOnboardingChecklist (Book J SAA-03 §2/§4/BR-SAA-03-001).
 * One checklist per school (`UNIQUE (school_id)`).
 */
final class StartOnboardingChecklistAction extends Action
{
    public function execute(StartOnboardingChecklistData $data): OnboardingChecklist
    {
        return $this->transaction(fn (): OnboardingChecklist => OnboardingChecklist::create([
            'tenant_id' => $data->tenantId,
            'school_id' => $data->schoolId,
            'started_at' => Carbon::now(),
            'target_go_live_date' => $data->targetGoLiveDate?->toDateString(),
            'steps' => array_map(
                fn (array $step): array => ['key' => $step['key'], 'label' => $step['label'], 'completed_at' => null, 'owner' => null],
                $data->steps,
            ),
            'status' => 'in_progress',
            'assigned_success_manager' => $data->assignedSuccessManager,
        ]));
    }
}

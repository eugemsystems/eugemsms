<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Actions;

use App\Models\User;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Auth\UserType;
use Modules\Core\Models\School;
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
        if (! School::query()->where('tenant_id', $data->tenantId)->whereKey($data->schoolId)->exists()) {
            throw new InvalidArgumentException('That school does not belong to this tenant.');
        }

        if ($data->steps === [] || count($data->steps) > 50) {
            throw new InvalidArgumentException('A checklist needs between 1 and 50 steps.');
        }

        $keys = array_column($data->steps, 'key');

        if (count($keys) !== count($data->steps) || count(array_unique($keys)) !== count($keys)) {
            throw new InvalidArgumentException('Every step needs its own unique key.');
        }

        if (OnboardingChecklist::query()->withoutGlobalScopes()->where('school_id', $data->schoolId)->exists()) {
            throw new InvalidArgumentException('This school already has an onboarding checklist.');
        }

        if ($data->assignedSuccessManager !== null && ! User::query()->where('user_type', UserType::Vendor)->whereKey($data->assignedSuccessManager)->exists()) {
            throw new InvalidArgumentException('A success manager must be vendor staff.');
        }

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

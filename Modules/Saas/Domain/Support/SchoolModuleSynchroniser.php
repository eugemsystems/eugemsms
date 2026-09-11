<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Support;

use Modules\Core\Domain\Actions\Schools\ToggleSchoolModuleAction;
use Modules\Core\Domain\DataObjects\Schools\ToggleModuleData;
use Modules\Core\Models\SchoolModule;
use Modules\Saas\Models\SubscriptionPlan;

/**
 * Book J SAA-01 §4/BR-SAA-01-002 ⭐ — "entitlement always reflects the
 * current plan": every covered school ends this call with exactly the
 * plan's `included_modules` enabled, nothing more and nothing less.
 * Writes go through `ToggleSchoolModuleAction` (Book A CORE-02) — the
 * one owner of `school_modules` — never a second, parallel write path.
 */
final readonly class SchoolModuleSynchroniser
{
    public function __construct(
        private ToggleSchoolModuleAction $toggle,
    ) {}

    /**
     * @param  array<int, int>  $schoolIds
     */
    public function syncForPlan(array $schoolIds, SubscriptionPlan $plan): void
    {
        $included = $plan->included_modules;

        foreach ($schoolIds as $schoolId) {
            // withoutGlobalScopes(): this runs with no ambient
            // SchoolContext (a cross-school subscription sync, not a
            // single school's request) — see ToggleSchoolModuleAction's
            // own docblock for why the explicit school_id below stands
            // in for it.
            $currentlyEnabled = SchoolModule::withoutGlobalScopes()
                ->where('school_id', $schoolId)
                ->where('is_enabled', true)
                ->pluck('module_code')
                ->all();

            foreach ($included as $moduleCode) {
                if (! in_array($moduleCode, $currentlyEnabled, true)) {
                    $this->toggle->execute(new ToggleModuleData(schoolId: $schoolId, moduleCode: $moduleCode, enable: true));
                }
            }

            foreach (array_diff($currentlyEnabled, $included) as $moduleCode) {
                if ($moduleCode === 'CORE') {
                    continue;
                }

                $this->toggle->execute(new ToggleModuleData(schoolId: $schoolId, moduleCode: $moduleCode, enable: false));
            }
        }
    }
}

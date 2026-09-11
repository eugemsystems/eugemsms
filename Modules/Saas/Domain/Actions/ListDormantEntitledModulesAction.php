<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Core\Models\SchoolModule;
use Modules\Saas\Models\ModuleAdoptionScore;

/**
 * ACT-ListDormantEntitledModules (Book J SAA-03 §4/BR-SAA-03-007). A
 * module the school is entitled to but hasn't touched for the
 * configured number of consecutive months is a customer-success
 * opportunity — engagement, never an upsell email or a billing
 * dispute. This Action only surfaces the list; what a human does with
 * it is out of scope here.
 */
final class ListDormantEntitledModulesAction extends Action
{
    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    /**
     * @return array<int, string> module codes dormant for the full window
     */
    public function execute(int $schoolId): array
    {
        $months = (int) $this->settings->get('saas.dormant_module_sustained_months', new ScopeChain);

        $entitled = SchoolModule::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where('is_enabled', true)
            ->pluck('module_code');

        $dormant = [];

        foreach ($entitled as $moduleCode) {
            if ($this->isDormantThroughout($schoolId, $moduleCode, $months)) {
                $dormant[] = $moduleCode;
            }
        }

        return $dormant;
    }

    private function isDormantThroughout(int $schoolId, string $moduleCode, int $months): bool
    {
        $periods = collect(range(0, $months - 1))
            ->map(fn (int $offset): string => Carbon::today()->subMonthsNoOverflow($offset)->format('Y-m'));

        $scores = ModuleAdoptionScore::where('school_id', $schoolId)
            ->where('module_code', $moduleCode)
            ->whereIn('period_month', $periods)
            ->get()
            ->keyBy('period_month');

        if ($scores->count() < $months) {
            return false;
        }

        return $scores->every(fn (ModuleAdoptionScore $score): bool => ! $score->is_actively_used);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Illuminate\Support\Collection;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Stores\Domain\Events\UnderInsuranceDetected;
use Modules\Stores\Models\AssetInsurance;
use Modules\Stores\Models\FixedAsset;

/**
 * ACT-CheckUnderInsurance (Book H1 FIN-10 §6/BR-FIN-10-016/
 * AC-FIN-10-007). A policy's sum insured is compared against the
 * NET BOOK VALUE of what it actually covers — `covered_asset_ids`
 * when set, else every non-disposed asset in `category_id` — never
 * against original cost, since NBV is what would actually need
 * replacing today.
 */
final class CheckUnderInsuranceAction extends Action
{
    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    /**
     * @return Collection<int, AssetInsurance>
     */
    public function execute(int $schoolId): Collection
    {
        $scope = new ScopeChain(schoolId: $schoolId);
        $warningPercent = (float) $this->settings->get('assets.under_insurance_warning_percent', $scope);

        $policies = AssetInsurance::query()
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->get()
            ->filter(function (AssetInsurance $policy) use ($warningPercent): bool {
                $coveredNbv = $this->coveredNetBookValue($policy);

                if ($coveredNbv <= 0) {
                    return false;
                }

                $coveredPercent = $policy->sum_insured_minor / $coveredNbv * 100;

                return $coveredPercent < $warningPercent;
            });

        foreach ($policies as $policy) {
            event(new UnderInsuranceDetected($policy, $this->coveredNetBookValue($policy)));
        }

        return $policies;
    }

    private function coveredNetBookValue(AssetInsurance $policy): int
    {
        $query = FixedAsset::query()->where('school_id', $policy->school_id)->where('status', '!=', 'disposed');

        if ($policy->covered_asset_ids !== null) {
            $query->whereIn('id', $policy->covered_asset_ids);
        } else {
            $query->where('category_id', $policy->category_id);
        }

        return (int) $query->sum('net_book_value_minor');
    }
}

<?php

declare(strict_types=1);

namespace Modules\Farm\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Farm\Domain\DataObjects\CreateLivestockData;
use Modules\Farm\Models\Livestock;
use Modules\Stores\Domain\Actions\CapitalizeAssetAction;
use Modules\Stores\Domain\DataObjects\CapitalizeAssetData;

/**
 * ACT-CreateLivestock (Book H2 OPS-03 §2/BR-OPS-03-010/011). Breeding
 * stock capitalises for real through `FIN-10`'s own
 * `CapitalizeAssetAction` when `farm.capitalise_breeding_stock` is on
 * and the acquisition cost meets
 * `farm.breeding_stock_capitalisation_threshold_minor` — stock held
 * for slaughter never capitalises regardless of cost, it stays
 * inventory.
 */
final class CreateLivestockAction extends Action
{
    public function __construct(
        private readonly SettingResolver $settings,
        private readonly CapitalizeAssetAction $capitalizeAsset,
    ) {}

    public function execute(CreateLivestockData $data): Livestock
    {
        return $this->transaction(function () use ($data): Livestock {
            $livestock = Livestock::create([
                'school_id' => $data->schoolId,
                'production_unit_id' => $data->productionUnitId,
                'tag_number' => $data->tagNumber,
                'species' => $data->species,
                'breed' => $data->breed,
                'is_herd_record' => $data->isHerdRecord,
                'head_count' => $data->headCount,
                'sex' => $data->sex,
                'date_of_birth' => $data->dateOfBirth?->toDateString(),
                'acquired_on' => $data->acquiredOn?->toDateString(),
                'acquisition_type' => $data->acquisitionType,
                'acquisition_cost_minor' => $data->acquisitionCostMinor,
                'currency' => $data->currency,
                'purpose' => $data->purpose,
                'status' => 'active',
            ]);

            if ($this->shouldCapitalize($data)) {
                $asset = $this->capitalizeAsset->execute(new CapitalizeAssetData(
                    schoolId: $data->schoolId,
                    academicYearId: (int) $data->academicYearId,
                    termId: (int) $data->termId,
                    categoryId: (int) $data->capitalizeCategoryId,
                    name: "Breeding {$data->species}".($data->tagNumber !== null ? " #{$data->tagNumber}" : ''),
                    acquisitionDate: $data->acquiredOn ?? now(),
                    acquisitionCostMinor: (int) $data->acquisitionCostMinor,
                    currency: (string) $data->currency,
                    acquisitionSource: $data->acquisitionType ?? 'purchase',
                    costCentreId: (int) $data->capitalizeCostCentreId,
                    contraAccountId: (int) $data->capitalizeContraAccountId,
                    performedByUserId: (int) $data->performedByUserId,
                ));

                $livestock->update(['fixed_asset_id' => $asset->id]);
            }

            return $livestock;
        });
    }

    private function shouldCapitalize(CreateLivestockData $data): bool
    {
        if ($data->purpose !== 'breeding' || $data->acquisitionCostMinor === null) {
            return false;
        }

        if ($data->capitalizeCategoryId === null || $data->capitalizeCostCentreId === null
            || $data->capitalizeContraAccountId === null || $data->academicYearId === null
            || $data->termId === null || $data->performedByUserId === null || $data->currency === null) {
            return false;
        }

        $scope = new ScopeChain(schoolId: $data->schoolId);

        if (! (bool) $this->settings->get('farm.capitalise_breeding_stock', $scope)) {
            return false;
        }

        $threshold = (int) $this->settings->get('farm.breeding_stock_capitalisation_threshold_minor', $scope);

        return $data->acquisitionCostMinor >= $threshold;
    }
}

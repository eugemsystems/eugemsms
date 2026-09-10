<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Stores\Domain\DataObjects\RecordInsurancePolicyData;
use Modules\Stores\Models\AssetInsurance;

final class RecordInsurancePolicyAction extends Action
{
    public function execute(RecordInsurancePolicyData $data): AssetInsurance
    {
        if ($data->coveredAssetIds === null && $data->categoryId === null) {
            throw ValidationException::withMessages([
                'categoryId' => 'A policy must cover either specific assets or a whole category.',
            ]);
        }

        return $this->transaction(fn (): AssetInsurance => AssetInsurance::create([
            'school_id' => $data->schoolId,
            'policy_number' => $data->policyNumber,
            'insurer' => $data->insurer,
            'policy_type' => $data->policyType,
            'covered_asset_ids' => $data->coveredAssetIds,
            'category_id' => $data->categoryId,
            'sum_insured_minor' => $data->sumInsuredMinor,
            'currency' => $data->currency,
            'premium_minor' => $data->premiumMinor,
            'starts_on' => $data->startsOn->toDateString(),
            'expires_on' => $data->expiresOn->toDateString(),
            'status' => 'active',
        ]));
    }
}

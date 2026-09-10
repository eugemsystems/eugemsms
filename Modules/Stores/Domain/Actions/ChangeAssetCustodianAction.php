<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Stores\Models\AssetMovement;
use Modules\Stores\Models\FixedAsset;

final class ChangeAssetCustodianAction extends Action
{
    public function execute(int $assetId, ?int $newCustodianStaffId, int $performedByUserId, ?string $reason = null): FixedAsset
    {
        $asset = FixedAsset::findOrFail($assetId);
        $from = $asset->custodian_staff_id;

        return $this->transaction(function () use ($asset, $from, $newCustodianStaffId, $performedByUserId, $reason): FixedAsset {
            $asset->update(['custodian_staff_id' => $newCustodianStaffId]);

            AssetMovement::create([
                'school_id' => $asset->school_id,
                'asset_id' => $asset->id,
                'movement_type' => 'custodian_change',
                'from_value' => $from !== null ? (string) $from : null,
                'to_value' => $newCustodianStaffId !== null ? (string) $newCustodianStaffId : null,
                'reason' => $reason,
                'performed_by' => $performedByUserId,
                'occurred_at' => Carbon::now(),
            ]);

            return $asset;
        });
    }
}

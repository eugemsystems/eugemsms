<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Stores\Domain\Events\AssetTransferred;
use Modules\Stores\Models\AssetMovement;
use Modules\Stores\Models\FixedAsset;

/**
 * ACT-TransferAsset (Book H1 FIN-10 §6/BR-FIN-10-009/010). Moves
 * FUTURE depreciation to the new cost centre from today — prior
 * `depreciation_entries` rows are never restated, since they are
 * append-only history of what was actually posted at the time.
 */
final class TransferAssetAction extends Action
{
    public function execute(int $assetId, int $newCostCentreId, int $performedByUserId, ?string $reason = null): FixedAsset
    {
        $asset = FixedAsset::findOrFail($assetId);
        $fromCostCentreId = $asset->cost_centre_id;

        return $this->transaction(function () use ($asset, $newCostCentreId, $fromCostCentreId, $performedByUserId, $reason): FixedAsset {
            $asset->update(['cost_centre_id' => $newCostCentreId]);

            AssetMovement::create([
                'school_id' => $asset->school_id,
                'asset_id' => $asset->id,
                'movement_type' => 'transfer',
                'from_value' => (string) $fromCostCentreId,
                'to_value' => (string) $newCostCentreId,
                'reason' => $reason,
                'performed_by' => $performedByUserId,
                'occurred_at' => Carbon::now(),
            ]);

            event(new AssetTransferred($asset));

            return $asset;
        });
    }
}

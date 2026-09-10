<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Stores\Models\AssetMovement;
use Modules\Stores\Models\FixedAsset;

/**
 * ACT-ChangeAssetStatus (Book H1 FIN-10 §6/BR-FIN-10-009). Covers
 * both status (`active`/`in_maintenance`/`idle`/...) and condition
 * (`good`/...) changes — each writes its own append-only movement
 * row, distinguished by `movement_type`.
 */
final class ChangeAssetStatusAction extends Action
{
    public function execute(int $assetId, ?string $newStatus, ?string $newCondition, int $performedByUserId, ?string $reason = null): FixedAsset
    {
        $asset = FixedAsset::findOrFail($assetId);

        return $this->transaction(function () use ($asset, $newStatus, $newCondition, $performedByUserId, $reason): FixedAsset {
            if ($newStatus !== null && $newStatus !== $asset->status) {
                $fromStatus = $asset->status;
                $asset->update(['status' => $newStatus]);

                AssetMovement::create([
                    'school_id' => $asset->school_id,
                    'asset_id' => $asset->id,
                    'movement_type' => 'status_change',
                    'from_value' => $fromStatus,
                    'to_value' => $newStatus,
                    'reason' => $reason,
                    'performed_by' => $performedByUserId,
                    'occurred_at' => Carbon::now(),
                ]);
            }

            if ($newCondition !== null && $newCondition !== $asset->condition) {
                $fromCondition = $asset->condition;
                $asset->update(['condition' => $newCondition]);

                AssetMovement::create([
                    'school_id' => $asset->school_id,
                    'asset_id' => $asset->id,
                    'movement_type' => 'condition_change',
                    'from_value' => $fromCondition,
                    'to_value' => $newCondition,
                    'reason' => $reason,
                    'performed_by' => $performedByUserId,
                    'occurred_at' => Carbon::now(),
                ]);
            }

            return $asset;
        });
    }
}

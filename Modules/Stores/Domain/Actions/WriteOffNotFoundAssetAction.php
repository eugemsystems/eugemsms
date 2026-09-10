<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Stores\Models\AssetMovement;
use Modules\Stores\Models\AssetVerification;
use Modules\Stores\Models\FixedAsset;

/**
 * ACT-WriteOffNotFoundAsset (Book H1 FIN-10 §6/BR-FIN-10-012 ⭐/
 * AC-FIN-10-005). The only path that can take an asset off active
 * status after a failed verification — requires the verification to
 * actually be `not_found` and a different user from whoever recorded
 * that scan, matching this codebase's own approval-separation
 * convention rather than letting one person both fail to find it and
 * write it off unquestioned.
 */
final class WriteOffNotFoundAssetAction extends Action
{
    public function execute(int $verificationId, int $approvedByUserId, string $reason): FixedAsset
    {
        $verification = AssetVerification::with('asset')->findOrFail($verificationId);

        if ($verification->status !== 'not_found') {
            throw ValidationException::withMessages([
                'verificationId' => "Verification #{$verification->id} is not marked not-found — nothing to write off.",
            ]);
        }

        if ($verification->verified_by !== null && $verification->verified_by === $approvedByUserId) {
            throw ValidationException::withMessages([
                'approvedByUserId' => 'The person who recorded the failed scan cannot also approve the write-off (BR-FIN-10-012).',
            ]);
        }

        $asset = $verification->asset;

        return $this->transaction(function () use ($asset, $verification, $approvedByUserId, $reason): FixedAsset {
            $fromStatus = $asset->status;
            $asset->update(['status' => 'written_off']);

            AssetMovement::create([
                'school_id' => $asset->school_id,
                'asset_id' => $asset->id,
                'movement_type' => 'status_change',
                'from_value' => $fromStatus,
                'to_value' => 'written_off',
                'reason' => "Not found in verification round {$verification->verification_round}: {$reason}",
                'performed_by' => $approvedByUserId,
                'occurred_at' => Carbon::now(),
            ]);

            $verification->update(['status' => 'resolved', 'discrepancy_note' => $reason]);

            return $asset;
        });
    }
}

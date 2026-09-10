<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Stores\Domain\Events\AssetNotFound;
use Modules\Stores\Models\AssetVerification;

/**
 * ACT-RecordAssetVerificationScan (Book H1 FIN-10 §6/BR-FIN-10-011/
 * 012 ⭐). A scan in an unexpected location is a discrepancy on the
 * VERIFICATION row, never a failure of the asset itself — the asset's
 * own `location` field is not silently corrected here. An asset not
 * found is flagged for investigation; it is never removed by this
 * action alone (BR-FIN-10-012 ⭐ — see `WriteOffNotFoundAssetAction`
 * for the only path that can actually take it off the register, and
 * that one requires approval).
 */
final class RecordAssetVerificationScanAction extends Action
{
    public function execute(int $verificationId, bool $found, ?string $actualLocation, ?string $conditionObserved, string $scanMethod, int $verifiedByUserId): AssetVerification
    {
        $verification = AssetVerification::with('asset')->findOrFail($verificationId);
        $expectedLocation = $verification->asset->location;
        $locationConfirmed = $actualLocation === null || $expectedLocation === null || $actualLocation === $expectedLocation;

        return $this->transaction(function () use ($verification, $found, $actualLocation, $conditionObserved, $scanMethod, $verifiedByUserId, $locationConfirmed): AssetVerification {
            $verification->update([
                'verified_on' => Carbon::now()->toDateString(),
                'found' => $found,
                'location_confirmed' => $locationConfirmed,
                'actual_location' => $actualLocation,
                'condition_observed' => $conditionObserved,
                'scan_method' => $scanMethod,
                'verified_by' => $verifiedByUserId,
                'discrepancy_note' => ! $locationConfirmed ? "Found at {$actualLocation}, expected elsewhere." : null,
                'status' => $found ? ($locationConfirmed ? 'verified' : 'discrepancy') : 'not_found',
            ]);

            if ($found) {
                $verification->asset->update(['last_verified_on' => Carbon::now()->toDateString()]);
            } else {
                event(new AssetNotFound($verification));
            }

            return $verification;
        });
    }
}

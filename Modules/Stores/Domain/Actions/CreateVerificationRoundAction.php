<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Stores\Models\AssetVerification;
use Modules\Stores\Models\FixedAsset;

/**
 * ACT-CreateVerificationRound (Book H1 FIN-10 §6/BR-FIN-10-011). One
 * row created up front for every active asset due — an asset that is
 * never scanned during the round stays visibly `pending`, not
 * silently absent from it (the same structural guarantee
 * `AssetVerification`'s own migration docblock describes).
 */
final class CreateVerificationRoundAction extends Action
{
    public function execute(int $schoolId, string $verificationRound, ?int $categoryId = null): int
    {
        $assets = FixedAsset::query()
            ->where('school_id', $schoolId)
            ->where('status', '!=', 'disposed')
            ->when($categoryId !== null, fn ($q) => $q->where('category_id', $categoryId))
            ->get();

        return $this->transaction(function () use ($schoolId, $verificationRound, $assets): int {
            foreach ($assets as $asset) {
                AssetVerification::create([
                    'school_id' => $schoolId,
                    'verification_round' => $verificationRound,
                    'asset_id' => $asset->id,
                    'status' => 'pending',
                ]);
            }

            return $assets->count();
        });
    }
}

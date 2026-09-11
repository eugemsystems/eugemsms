<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Contracts\Install\LicenceClient;
use Modules\Saas\Models\LicenceKey;

/**
 * ACT-ValidateLicenceKey (Book J SAA-01 §4/BR-SAA-01-007 ⭐). Checks
 * online where possible; when the licence server is unreachable, falls
 * back to `offline_grace_days` of continued operation from
 * `last_validated_at` — never an immediate lockout for a connectivity
 * problem, consistent with `CORE-01`'s own installation-time grace
 * period (`ActivateLicenceAction`).
 */
final class ValidateLicenceKeyAction extends Action
{
    public function __construct(
        private readonly LicenceClient $client,
    ) {}

    public function execute(int $licenceKeyId): LicenceKey
    {
        $licenceKey = LicenceKey::query()->findOrFail($licenceKeyId);
        $response = $this->client->validate($licenceKey->key_value, $licenceKey->installation_uuid ?? '');

        return $this->transaction(function () use ($licenceKey, $response): LicenceKey {
            if ($response->reachable) {
                $licenceKey->update([
                    'status' => $response->valid ? 'active' : 'revoked',
                    'last_validated_at' => Carbon::now(),
                ]);

                return $licenceKey->fresh();
            }

            // Unreachable — honour the offline grace window rather than
            // penalising a connectivity problem (BR-SAA-01-007).
            if ($licenceKey->isWithinOfflineGrace() || $licenceKey->last_validated_at === null) {
                return $licenceKey;
            }

            $licenceKey->update(['status' => 'expired']);

            return $licenceKey->fresh();
        });
    }
}

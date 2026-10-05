<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Actions;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\Saas\Models\ReleaseDeployment;

/**
 * ACT-ConfirmReleaseStable (Book J SAA-02 §3/§4/BR-SAA-02-005
 * (AC-SAA-02-003)). The moment rollback stops being available —
 * everything before this call could still roll back; nothing after
 * can.
 */
final class ConfirmReleaseStableAction extends Action
{
    public function execute(int $releaseDeploymentId): ReleaseDeployment
    {
        $deployment = ReleaseDeployment::query()->findOrFail($releaseDeploymentId);

        if (! $deployment->rollback_available || $deployment->migration_status === 'rolled_back') {
            throw new InvalidArgumentException('Only a live canary can be confirmed stable — this one is already confirmed or has been rolled back.');
        }

        return $this->transaction(function () use ($deployment): ReleaseDeployment {
            $deployment->update([
                'deployment_stage' => 'general',
                'migration_status' => 'completed',
                'completed_at' => Carbon::now(),
                'rollback_available' => false,
            ]);

            return $deployment->fresh();
        });
    }
}

<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Saas\Domain\Exceptions\RollbackNoLongerAvailableException;
use Modules\Saas\Models\ReleaseDeployment;

/**
 * ACT-RollbackRelease (Book J SAA-02 §3/§4/BR-SAA-02-005
 * (AC-SAA-02-003)). Refuses once `ConfirmReleaseStableAction` has
 * already run.
 */
final class RollbackReleaseAction extends Action
{
    public function execute(int $releaseDeploymentId): ReleaseDeployment
    {
        $deployment = ReleaseDeployment::query()->findOrFail($releaseDeploymentId);

        if (! $deployment->rollback_available) {
            throw new RollbackNoLongerAvailableException(
                "Release deployment [{$deployment->id}] has already been confirmed stable — rollback is no longer available."
            );
        }

        return $this->transaction(function () use ($deployment): ReleaseDeployment {
            $deployment->update(['migration_status' => 'rolled_back']);

            return $deployment->fresh();
        });
    }
}

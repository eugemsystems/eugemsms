<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Saas\Domain\DataObjects\StartCanaryReleaseData;
use Modules\Saas\Models\ReleaseDeployment;

/**
 * ACT-StartCanaryRelease (Book J SAA-02 §3/§4/BR-SAA-02-005
 * (AC-SAA-02-003)). Rollback starts available and stays that way
 * until `ConfirmReleaseStableAction` explicitly revokes it.
 */
final class StartCanaryReleaseAction extends Action
{
    public function execute(StartCanaryReleaseData $data): ReleaseDeployment
    {
        return $this->transaction(fn (): ReleaseDeployment => ReleaseDeployment::create([
            'version' => $data->version,
            'deployment_stage' => 'canary',
            'canary_tenant_ids' => $data->canaryTenantIds,
            'migration_status' => 'running',
            'started_at' => Carbon::now(),
            'rollback_available' => true,
        ]));
    }
}

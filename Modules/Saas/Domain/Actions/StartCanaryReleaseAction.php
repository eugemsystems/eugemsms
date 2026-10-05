<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Actions;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\Tenant;
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
        if (preg_match('/^\d+\.\d+\.\d+(-[A-Za-z0-9.]+)?$/', $data->version) !== 1) {
            throw new InvalidArgumentException('A version looks like 1.4.0.');
        }

        $canaryTenantIds = array_values(array_unique($data->canaryTenantIds));

        if ($canaryTenantIds === [] || Tenant::query()->whereIn('id', $canaryTenantIds)->count() !== count($canaryTenantIds)) {
            throw new InvalidArgumentException('A canary needs at least one existing tenant.');
        }

        if (ReleaseDeployment::query()->where('version', $data->version)->exists()) {
            throw new InvalidArgumentException("Version {$data->version} has already been deployed.");
        }

        return $this->transaction(fn (): ReleaseDeployment => ReleaseDeployment::create([
            'version' => $data->version,
            'deployment_stage' => 'canary',
            'canary_tenant_ids' => $canaryTenantIds,
            'migration_status' => 'running',
            'started_at' => Carbon::now(),
            'rollback_available' => true,
        ]));
    }
}

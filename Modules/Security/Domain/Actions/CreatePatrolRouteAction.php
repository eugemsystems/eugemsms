<?php

declare(strict_types=1);

namespace Modules\Security\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Security\Domain\DataObjects\CreatePatrolRouteData;
use Modules\Security\Models\PatrolRoute;

/**
 * ACT-CreatePatrolRoute (Book H2 OPS-06 §2).
 */
final class CreatePatrolRouteAction extends Action
{
    public function execute(CreatePatrolRouteData $data): PatrolRoute
    {
        return $this->transaction(fn (): PatrolRoute => PatrolRoute::create([
            'school_id' => $data->schoolId,
            'code' => $data->code,
            'name' => $data->name,
            'checkpoint_ids' => $data->checkpointIds,
            'expected_duration_min' => $data->expectedDurationMin,
            'frequency' => $data->frequency,
            'is_active' => true,
        ]));
    }
}

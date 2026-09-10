<?php

declare(strict_types=1);

namespace Modules\Security\Domain\Actions;

use Carbon\CarbonInterface;
use Modules\Core\Domain\Actions\Action;
use Modules\Security\Models\Patrol;
use Modules\Security\Models\PatrolRoute;

/**
 * ACT-SchedulePatrol (Book H2 OPS-06 §2). `checkpoints_expected`
 * comes straight from the route's own checkpoint list — the real
 * count `CompletePatrolAction`/`CheckMissedPatrolsAction` measure
 * against.
 */
final class SchedulePatrolAction extends Action
{
    public function execute(int $patrolRouteId, int $guardStaffId, CarbonInterface $scheduledAt): Patrol
    {
        $route = PatrolRoute::findOrFail($patrolRouteId);

        return $this->transaction(fn (): Patrol => Patrol::create([
            'school_id' => $route->school_id,
            'patrol_route_id' => $route->id,
            'guard_staff_id' => $guardStaffId,
            'scheduled_at' => $scheduledAt,
            'checkpoints_expected' => count($route->checkpoint_ids),
            'checkpoints_scanned' => 0,
            'status' => 'scheduled',
        ]));
    }
}

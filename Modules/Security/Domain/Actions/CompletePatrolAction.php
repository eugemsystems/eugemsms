<?php

declare(strict_types=1);

namespace Modules\Security\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Security\Domain\Events\PatrolIncomplete;
use Modules\Security\Models\Patrol;

/**
 * ACT-CompletePatrol (Book H2 OPS-06 §4/BR-OPS-06-004). Fewer scans
 * than the route's own expected checkpoint count marks the patrol
 * `incomplete` and reports it — never silently `completed`.
 */
final class CompletePatrolAction extends Action
{
    public function execute(int $patrolId, ?string $findings = null): Patrol
    {
        $patrol = Patrol::findOrFail($patrolId);
        $isComplete = $patrol->checkpoints_scanned >= $patrol->checkpoints_expected;

        return $this->transaction(function () use ($patrol, $findings, $isComplete): Patrol {
            $patrol->update([
                'completed_at' => now(),
                'status' => $isComplete ? 'completed' : 'incomplete',
                'findings' => $findings,
            ]);

            if (! $isComplete) {
                event(new PatrolIncomplete($patrol));
            }

            return $patrol;
        });
    }
}

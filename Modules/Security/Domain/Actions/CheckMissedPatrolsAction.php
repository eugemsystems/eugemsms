<?php

declare(strict_types=1);

namespace Modules\Security\Domain\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Core\Domain\Actions\Action;
use Modules\Security\Domain\Events\PatrolMissed;
use Modules\Security\Models\Patrol;

/**
 * ACT-CheckMissedPatrols (Book H2 OPS-06 §4/BR-OPS-06-005). A patrol
 * still `scheduled` well past its own scheduled time never started at
 * all — distinct from `incomplete` (started, fell short) — and alerts
 * the security supervisor.
 */
final class CheckMissedPatrolsAction extends Action
{
    private const int GRACE_MINUTES = 15;

    /**
     * @return Collection<int, Patrol>
     */
    public function execute(int $schoolId): Collection
    {
        $cutoff = Carbon::now()->subMinutes(self::GRACE_MINUTES);

        $missed = Patrol::where('school_id', $schoolId)
            ->where('status', 'scheduled')
            ->where('scheduled_at', '<=', $cutoff)
            ->get();

        foreach ($missed as $patrol) {
            $patrol->update(['status' => 'missed']);
            event(new PatrolMissed($patrol));
        }

        return $missed;
    }
}

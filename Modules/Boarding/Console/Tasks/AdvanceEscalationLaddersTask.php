<?php

declare(strict_types=1);

namespace Modules\Boarding\Console\Tasks;

use Modules\Boarding\Domain\Actions\AdvanceEscalationLadderAction;
use Modules\Boarding\Models\MissingLearnerIncident;
use Modules\Core\Domain\Contracts\ScheduledTaskHandler;
use Modules\Core\Models\School;

/**
 * Scheduled (BRD-02 BR-BRD-02-010): advances every open missing-learner incident's escalation ladder on elapsed time, whether or not a step was acknowledged.
 */
final class AdvanceEscalationLaddersTask implements ScheduledTaskHandler
{
    public function handle(School $school): string
    {
        $count = 0;

        foreach (MissingLearnerIncident::query()->whereIn('status', ['open', 'escalating'])->pluck('id') as $incidentId) {
            app(AdvanceEscalationLadderAction::class)->execute((int) $incidentId);
            $count++;
        }

        return "{$count} incident(s) checked";
    }
}

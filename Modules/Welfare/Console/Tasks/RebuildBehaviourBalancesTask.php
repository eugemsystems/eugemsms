<?php

declare(strict_types=1);

namespace Modules\Welfare\Console\Tasks;

use Modules\Core\Domain\Contracts\ScheduledTaskHandler;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Welfare\Domain\Actions\RebuildBehaviourPointBalanceAction;
use Modules\Welfare\Models\BehaviourRecord;

/**
 * Scheduled (BRD-07): rebuilds the behaviour-point balance of every learner with a record this term, so the cached balance never drifts from the records.
 */
final class RebuildBehaviourBalancesTask implements ScheduledTaskHandler
{
    public function handle(School $school): string
    {
        $termId = Term::query()->where('starts_on', '<=', now())->orderByDesc('starts_on')->value('id');

        if ($termId === null) {
            return 'No current term.';
        }

        $count = 0;

        foreach (BehaviourRecord::query()->where('term_id', $termId)->distinct()->pluck('student_id') as $studentId) {
            app(RebuildBehaviourPointBalanceAction::class)->execute($school->id, (int) $studentId, (int) $termId);
            $count++;
        }

        return "{$count} balance(s) rebuilt";
    }
}

<?php

declare(strict_types=1);

namespace Modules\Academic\Console\Tasks;

use Modules\Academic\Domain\Actions\AutoSubmitExpiredAttemptsAction;
use Modules\Academic\Models\CbtCandidateAttempt;
use Modules\Core\Domain\Contracts\ScheduledTaskHandler;
use Modules\Core\Models\School;

/**
 * Scheduled (ACA-09 BR-ACA-09-002): submits every CBT attempt whose time has run out, with whatever the candidate had saved.
 */
final class AutoSubmitExpiredCbtAttemptsTask implements ScheduledTaskHandler
{
    public function handle(School $school): string
    {
        $count = 0;

        foreach (CbtCandidateAttempt::query()->whereIn('status', ['in_progress', 'flagged'])->distinct()->pluck('test_id') as $testId) {
            $count += app(AutoSubmitExpiredAttemptsAction::class)->execute((int) $testId);
        }

        return "{$count} attempt(s) auto-submitted";
    }
}

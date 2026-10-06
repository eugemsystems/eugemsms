<?php

declare(strict_types=1);

namespace Modules\Boarding\Console\Tasks;

use Modules\Boarding\Domain\Actions\CheckOverdueExeatAction;
use Modules\Boarding\Models\Exeat;
use Modules\Core\Domain\Contracts\ScheduledTaskHandler;
use Modules\Core\Models\School;

/**
 * Scheduled (BRD-03 BR-BRD-03-015): notifies on every departed exeat past its return time and opens a missing-learner incident once the configured window passes.
 */
final class CheckOverdueExeatsTask implements ScheduledTaskHandler
{
    public function handle(School $school): string
    {
        $count = 0;

        foreach (Exeat::query()->whereIn('status', ['departed', 'overdue'])->where('returns_by', '<', now())->pluck('id') as $exeatId) {
            app(CheckOverdueExeatAction::class)->execute((int) $exeatId);
            $count++;
        }

        return "{$count} overdue exeat(s) checked";
    }
}

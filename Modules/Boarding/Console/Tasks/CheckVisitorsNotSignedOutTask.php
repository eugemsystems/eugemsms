<?php

declare(strict_types=1);

namespace Modules\Boarding\Console\Tasks;

use Modules\Boarding\Domain\Actions\CheckVisitorNotSignedOutAction;
use Modules\Boarding\Models\VisitorLogEntry;
use Modules\Core\Domain\Contracts\ScheduledTaskHandler;
use Modules\Core\Models\School;

/**
 * Scheduled (BRD-03 BR-BRD-03-020): flags every visitor still signed in at the evening check.
 */
final class CheckVisitorsNotSignedOutTask implements ScheduledTaskHandler
{
    public function handle(School $school): string
    {
        $count = 0;

        foreach (VisitorLogEntry::query()->whereNull('signed_out_at')->pluck('id') as $entryId) {
            if (app(CheckVisitorNotSignedOutAction::class)->execute((int) $entryId)) {
                $count++;
            }
        }

        return "{$count} visitor(s) still on site";
    }
}

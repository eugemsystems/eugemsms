<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Contracts;

use Modules\Core\Models\School;

/**
 * One scheduled job's work for ONE school. `serp:run-task` loops the active
 * schools, sets the school context for each, and calls this; a handler only
 * calls existing Actions and returns a short summary for the run record.
 */
interface ScheduledTaskHandler
{
    public function handle(School $school): string;
}

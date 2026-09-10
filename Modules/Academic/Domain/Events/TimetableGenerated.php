<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Events;

use Modules\Academic\Models\Timetable;
use Modules\Academic\Models\TimetableGenerationRun;

/**
 * Book E ACA-03 §9.
 */
final class TimetableGenerated
{
    public function __construct(
        public readonly Timetable $timetable,
        public readonly TimetableGenerationRun $run,
    ) {}
}

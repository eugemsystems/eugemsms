<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Events;

use Modules\Academic\Models\Timetable;

final class TimetableSuperseded
{
    public function __construct(public readonly Timetable $timetable) {}
}

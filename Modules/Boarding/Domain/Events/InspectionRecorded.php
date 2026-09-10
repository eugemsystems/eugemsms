<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Events;

use Modules\Boarding\Models\RoomInspection;

final class InspectionRecorded
{
    public function __construct(
        public readonly RoomInspection $inspection,
    ) {}
}

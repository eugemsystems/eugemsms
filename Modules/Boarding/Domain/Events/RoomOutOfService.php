<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Events;

use Modules\Boarding\Models\HostelRoom;

final class RoomOutOfService
{
    public function __construct(
        public readonly HostelRoom $room,
    ) {}
}

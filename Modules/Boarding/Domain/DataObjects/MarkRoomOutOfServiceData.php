<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\DataObjects;

final readonly class MarkRoomOutOfServiceData
{
    public function __construct(
        public int $roomId,
        public string $reason,
    ) {}
}

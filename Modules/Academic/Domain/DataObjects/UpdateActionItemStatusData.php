<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class UpdateActionItemStatusData
{
    public function __construct(
        public int $meetingId,
        public int $actionItemIndex,
        public string $status,
    ) {}
}

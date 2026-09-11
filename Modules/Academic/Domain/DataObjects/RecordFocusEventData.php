<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class RecordFocusEventData
{
    public function __construct(
        public int $attemptId,
        public string $eventType,
    ) {}
}

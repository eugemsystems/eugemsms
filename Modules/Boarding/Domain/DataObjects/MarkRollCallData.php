<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\DataObjects;

final readonly class MarkRollCallData
{
    public function __construct(
        public int $rollCallId,
        public int $studentId,
        public string $status,
        public int $markedByUserId,
        public ?string $note = null,
        public ?string $deviceSource = null,
    ) {}
}

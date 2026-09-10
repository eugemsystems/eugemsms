<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class MarkAttendanceRecordInput
{
    public function __construct(
        public int $studentId,
        public string $status,
        public ?int $reasonCodeId = null,
        public ?int $minutesLate = null,
        public ?string $note = null,
        public ?string $idempotencyKey = null,
    ) {}
}

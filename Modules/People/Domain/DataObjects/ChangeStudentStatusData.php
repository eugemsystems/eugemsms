<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

final readonly class ChangeStudentStatusData
{
    public function __construct(
        public int $studentId,
        public string $newStatus,
        public int $changedByUserId,
        public ?string $reasonCode = null,
        public ?string $reason = null,
    ) {}
}

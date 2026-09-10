<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\DataObjects;

final readonly class RecordCheckpointMovementData
{
    public function __construct(
        public int $studentId,
        public int $checkpointId,
        public string $direction,
        public string $method,
        public ?int $recordedByUserId = null,
        public bool $hasActiveExeat = false,
        public ?string $note = null,
    ) {}
}

<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\DataObjects;

final readonly class CreateMovementCheckpointData
{
    public function __construct(
        public int $schoolId,
        public string $code,
        public string $name,
        public string $checkpointType,
        public bool $isBoundary = false,
        public ?string $hardwareDeviceId = null,
    ) {}
}

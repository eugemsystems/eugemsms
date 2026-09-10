<?php

declare(strict_types=1);

namespace Modules\Security\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class TriggerEmergencyDrillData
{
    public function __construct(
        public int $schoolId,
        public int $termId,
        public string $drillType,
        public int $conductedByUserId,
        public bool $isAnnounced = false,
        public ?CarbonInterface $conductedAt = null,
    ) {}
}

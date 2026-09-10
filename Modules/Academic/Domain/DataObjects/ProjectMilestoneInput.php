<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class ProjectMilestoneInput
{
    public function __construct(
        public int $sequence,
        public string $title,
        public CarbonInterface $dueOn,
        public float $weightPercent,
        public ?string $description = null,
        public bool $requiresEvidence = true,
    ) {}
}

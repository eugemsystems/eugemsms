<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class AdvanceDisciplinaryCaseStageData
{
    public function __construct(
        public int $caseId,
        public string $targetStage,
        public ?string $outcome = null,
        public ?CarbonInterface $outcomeDate = null,
    ) {}
}

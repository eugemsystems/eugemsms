<?php

declare(strict_types=1);

namespace Modules\Utilities\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class StartGeneratorRunData
{
    public function __construct(
        public int $schoolId,
        public int $termId,
        public int $generatorId,
        public CarbonInterface $startedAt,
        public string $reason,
        public ?string $loadSheddingStage = null,
        public ?int $operatedByUserId = null,
    ) {}
}

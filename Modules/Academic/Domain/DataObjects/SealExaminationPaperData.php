<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class SealExaminationPaperData
{
    public function __construct(
        public int $paperId,
        public CarbonInterface $releaseAt,
    ) {}
}

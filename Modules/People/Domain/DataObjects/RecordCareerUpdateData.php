<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class RecordCareerUpdateData
{
    public function __construct(
        public int $alumnusId,
        public string $updateType,
        public string $title,
        public ?string $institutionOrEmployer = null,
        public ?CarbonInterface $startsOn = null,
        public ?CarbonInterface $endsOn = null,
        public bool $isCurrent = false,
    ) {}
}

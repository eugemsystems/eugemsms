<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class RecordTopicDeliveryData
{
    public function __construct(
        public int $schemeOfWorkId,
        public int $plannedTopicIndex,
        public CarbonInterface $actualDeliveredOn,
        public ?string $varianceNote = null,
    ) {}
}

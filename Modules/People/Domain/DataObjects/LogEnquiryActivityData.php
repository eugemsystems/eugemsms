<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class LogEnquiryActivityData
{
    public function __construct(
        public int $enquiryId,
        public string $activityType,
        public string $summary,
        public int $performedByUserId,
        public ?string $outcome = null,
        public ?CarbonInterface $nextFollowUpOn = null,
        public ?CarbonInterface $occurredAt = null,
    ) {}
}

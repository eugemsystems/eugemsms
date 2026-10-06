<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

final readonly class AdvanceEnquiryStageData
{
    public function __construct(
        public int $enquiryId,
        public string $newStage,
        public ?string $lostReason = null,
    ) {}
}

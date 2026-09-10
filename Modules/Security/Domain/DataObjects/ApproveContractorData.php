<?php

declare(strict_types=1);

namespace Modules\Security\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class ApproveContractorData
{
    public function __construct(
        public CarbonInterface $insuranceExpiresOn,
        public CarbonInterface $safetyInductionOn,
        public CarbonInterface $inductionValidUntil,
        public int $approvedByUserId,
        public ?CarbonInterface $policeClearanceOn = null,
        public ?int $insuranceFileId = null,
    ) {}
}

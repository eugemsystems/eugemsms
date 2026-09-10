<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class IssueInvoicesForAssignmentData
{
    public function __construct(
        public int $assignmentId,
        public int $issuedByUserId,
        public ?string $batchUuid = null,
        public ?CarbonInterface $effectiveAt = null,
    ) {}
}

<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class GuardianApproveSubjectSelectionData
{
    public function __construct(
        public int $submissionId,
        public int $approvedByUserId,
    ) {}
}

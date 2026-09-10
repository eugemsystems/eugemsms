<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class SchoolApproveSubjectSelectionData
{
    public function __construct(
        public int $submissionId,
        public int $approvedByUserId,
    ) {}
}

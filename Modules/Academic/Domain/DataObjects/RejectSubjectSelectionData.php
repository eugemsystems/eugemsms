<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class RejectSubjectSelectionData
{
    public function __construct(
        public int $submissionId,
        public string $rejectionReason,
    ) {}
}

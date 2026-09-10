<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class ConfirmExaminationCandidateData
{
    public function __construct(
        public int $candidateId,
        public int $confirmedByUserId,
    ) {}
}

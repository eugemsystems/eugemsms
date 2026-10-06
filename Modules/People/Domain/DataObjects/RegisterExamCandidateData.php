<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

final readonly class RegisterExamCandidateData
{
    public function __construct(
        public int $examId,
        public int $applicationId,
    ) {}
}

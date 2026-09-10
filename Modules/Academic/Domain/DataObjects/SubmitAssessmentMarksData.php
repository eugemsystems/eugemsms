<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class SubmitAssessmentMarksData
{
    public function __construct(
        public int $assessmentId,
        public int $submittedByUserId,
    ) {}
}

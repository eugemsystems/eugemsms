<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

final readonly class SubmitSelfAssessmentData
{
    /**
     * @param  array<string, mixed>  $selfAssessment
     */
    public function __construct(
        public int $appraisalId,
        public array $selfAssessment,
    ) {}
}

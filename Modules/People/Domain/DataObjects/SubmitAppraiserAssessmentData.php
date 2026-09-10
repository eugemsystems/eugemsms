<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

final readonly class SubmitAppraiserAssessmentData
{
    /**
     * @param  array<string, mixed>  $appraiserAssessment
     * @param  array<string, mixed>|null  $objectives
     */
    public function __construct(
        public int $appraisalId,
        public array $appraiserAssessment,
        public ?string $overallRating = null,
        public ?string $developmentPlan = null,
        public ?array $objectives = null,
    ) {}
}

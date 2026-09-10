<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\DataObjects;

final readonly class RegisterProcessingActivityData
{
    /**
     * @param  array<int, string>  $dataCategories
     * @param  array<int, string>  $subjectCategories
     * @param  array<int, string>|null  $recipients
     */
    public function __construct(
        public int $schoolId,
        public string $activityName,
        public string $purpose,
        public string $lawfulBasis,
        public array $dataCategories,
        public array $subjectCategories,
        public string $owningModule,
        public bool $involvesMinors = false,
        public bool $isSpecialCategory = false,
        public ?array $recipients = null,
        public ?int $retentionScheduleId = null,
        public ?string $securityMeasures = null,
    ) {}
}

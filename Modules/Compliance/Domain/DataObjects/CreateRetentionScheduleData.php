<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\DataObjects;

final readonly class CreateRetentionScheduleData
{
    /**
     * @param  array<int, string>  $tableNames
     */
    public function __construct(
        public int $schoolId,
        public string $recordClass,
        public array $tableNames,
        public string $retentionYears,
        public string $retentionTrigger,
        public string $disposalMethod,
        public string $legalBasis,
        public bool $requiresReview = true,
    ) {}
}

<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\DataObjects;

final readonly class RecordDataBreachData
{
    /**
     * @param  array<int, string>  $dataCategories
     */
    public function __construct(
        public int $schoolId,
        public string $breachType,
        public string $description,
        public array $dataCategories,
        public string $severity,
        public bool $includesMinors,
        public int $reportedByUserId,
        public ?int $recordsAffected = null,
        public ?int $subjectsAffected = null,
    ) {}
}

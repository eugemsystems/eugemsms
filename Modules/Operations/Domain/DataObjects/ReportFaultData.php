<?php

declare(strict_types=1);

namespace Modules\Operations\Domain\DataObjects;

final readonly class ReportFaultData
{
    public function __construct(
        public int $schoolId,
        public int $termId,
        public string $location,
        public string $category,
        public string $description,
        public string $severity,
        public int $reportedByUserId,
        public ?int $maintenanceAssetId = null,
        public bool $affectsSafety = false,
        public bool $affectsTeaching = false,
        public ?string $sourceType = null,
        public ?int $sourceId = null,
        /** @var array<int, int>|null */
        public ?array $photoFileIds = null,
    ) {}
}

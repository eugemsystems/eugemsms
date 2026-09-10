<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\DataObjects;

final readonly class CreateCustomReportData
{
    /**
     * @param  array<int, array{entity: string, field: string, alias?: string}>  $selectedFields
     * @param  array<int, mixed>|null  $filters
     * @param  array<int, string>|null  $groupBy
     * @param  array<int, array{field: string, function: string}>|null  $aggregations
     * @param  array<int, mixed>|null  $sort
     */
    public function __construct(
        public int $schoolId,
        public string $name,
        public string $primaryEntityKey,
        public array $selectedFields,
        public int $createdByUserId,
        public ?string $description = null,
        public ?array $filters = null,
        public ?array $groupBy = null,
        public ?array $aggregations = null,
        public ?array $sort = null,
        public ?string $chartType = null,
    ) {}
}

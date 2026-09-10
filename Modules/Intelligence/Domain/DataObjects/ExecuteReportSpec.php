<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\DataObjects;

/**
 * Book J INT-01 §3 ⭐. What `ExecuteCustomReportAction` actually runs —
 * whether it came from a saved `CustomReport` or an unsaved ad hoc
 * query, the shape is identical.
 */
final readonly class ExecuteReportSpec
{
    /**
     * @param  array<int, array{entity: string, field: string, alias?: string}>  $selectedFields
     * @param  array<int, array{field: string, operator: string, value: mixed, group_id?: int}>  $filters
     * @param  array<int, string>  $groupBy
     * @param  array<int, array{field: string, function: string}>  $aggregations
     * @param  array<int, int>|null  $consolidateSchoolIds
     */
    public function __construct(
        public int $schoolId,
        public string $primaryEntityKey,
        public array $selectedFields,
        public array $filters = [],
        public array $groupBy = [],
        public array $aggregations = [],
        public ?array $consolidateSchoolIds = null,
    ) {}
}

<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Intelligence\Models\WarehouseSnapshot;

/**
 * ACT-GetEnrolmentComparative (Book J INT-02 §3/BR-INT-02-008
 * (AC-INT-02-004)). A multi-year enrolment comparative reads the
 * `student` entity's own row-count history from `warehouse_snapshots`
 * (INT-01's `RebuildWarehouseSnapshotAction`) — it never re-aggregates
 * `Modules\People\Models\Student` live for any year but the current
 * one, which is exactly what a warehouse snapshot exists to avoid.
 */
final class GetEnrolmentComparativeAction extends Action
{
    protected bool $transactional = false;

    /**
     * @param  array<int, int>  $years  calendar years to compare, e.g. [2024, 2025, 2026]
     * @return array<int, array{year: int, snapshotDate: ?string, studentCount: ?int}>
     */
    public function execute(int $schoolId, array $years): array
    {
        return collect($years)->map(function (int $year) use ($schoolId): array {
            $snapshot = WarehouseSnapshot::where('school_id', $schoolId)
                ->where('entity_key', 'student')
                ->whereYear('snapshot_date', $year)
                ->orderByDesc('snapshot_date')
                ->first();

            return [
                'year' => $year,
                'snapshotDate' => $snapshot?->snapshot_date->toDateString(),
                'studentCount' => $snapshot?->row_count,
            ];
        })->all();
    }
}

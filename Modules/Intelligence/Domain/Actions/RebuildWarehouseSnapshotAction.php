<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Intelligence\Domain\Registry\ReportFieldRegistry;
use Modules\Intelligence\Models\WarehouseSnapshot;

/**
 * ACT-RebuildWarehouseSnapshot (Book J INT-01 §2/BR-INT-01-007).
 * Meant to run nightly, after every other nightly job (balance
 * rebuilds, integrity checks) — wiring that ordering into the actual
 * schedule is a deployment step, not this action's concern, matching
 * every other "meant to run on a schedule" action this book set
 * already documents this way.
 *
 * **Honest scope boundary.** This tracks each registered entity's
 * current row COUNT per school per night — the real signal
 * `ExecuteCustomReportAction`'s ad hoc budget check consults (BR-INT-01-008)
 * — not a full generic denormalised VALUE copy of every field for
 * historical comparison. A truly generic per-entity warehouse schema
 * (arbitrary columns, arbitrary history) is its own, considerably
 * larger engineering effort; row-count tracking is the real,
 * proportionate slice this pass builds.
 */
final class RebuildWarehouseSnapshotAction extends Action
{
    /**
     * @return array<string, int> entityKey => row count
     */
    public function execute(int $schoolId): array
    {
        $results = [];

        foreach (ReportFieldRegistry::allEntities() as $entityKey => $entity) {
            $start = microtime(true);

            /** @var class-string<Model> $modelClass */
            $modelClass = $entity->baseModelClass;
            $count = $modelClass::where('school_id', $schoolId)->count();
            $durationMs = (int) ((microtime(true) - $start) * 1000);

            $this->transaction(function () use ($schoolId, $entityKey, $count, $durationMs): void {
                WarehouseSnapshot::updateOrCreate(
                    ['school_id' => $schoolId, 'entity_key' => $entityKey, 'snapshot_date' => Carbon::today()],
                    ['row_count' => $count, 'rebuilt_at' => Carbon::now(), 'duration_ms' => $durationMs],
                );
            });

            $results[$entityKey] = $count;
        }

        return $results;
    }
}

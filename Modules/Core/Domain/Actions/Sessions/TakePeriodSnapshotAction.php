<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Sessions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Contracts\Sessions\SnapshotPayloadProvider;
use Modules\Core\Domain\DataObjects\Sessions\SnapshotData;
use Modules\Core\Domain\Events\Sessions\SnapshotTaken;
use Modules\Core\Domain\Support\Sessions\CanonicalPayloadHasher;
use Modules\Core\Models\PeriodSnapshot;
use Modules\Core\Models\Term;

/**
 * ACT-TakePeriodSnapshot (Book A CORE-03 §4). BR-CORE-03-015: hashes a
 * canonically-serialised payload and chains it to the school's prior
 * snapshot. BR-CORE-03-016: snapshots are never deleted or edited —
 * enforced on `PeriodSnapshot` itself, not just here.
 */
final class TakePeriodSnapshotAction extends Action
{
    public function __construct(
        private readonly SnapshotPayloadProvider $payloadProvider,
    ) {}

    public function execute(SnapshotData $data): PeriodSnapshot
    {
        $term = Term::withoutGlobalScopes()->findOrFail($data->termId);

        return $this->transaction(function () use ($data, $term): PeriodSnapshot {
            $payload = $this->payloadProvider->payload($term);
            $rowCounts = $this->payloadProvider->rowCounts($term);

            $previousHash = PeriodSnapshot::withoutGlobalScopes()
                ->where('school_id', $data->schoolId)
                ->orderByDesc('id')
                ->value('payload_hash');

            $snapshot = PeriodSnapshot::create([
                'school_id' => $data->schoolId,
                'academic_year_id' => $data->academicYearId,
                'term_id' => $data->termId,
                'snapshot_type' => $data->snapshotType,
                'taken_at' => now(),
                'taken_by' => $data->takenByUserId,
                'payload' => $payload,
                'payload_hash' => CanonicalPayloadHasher::hash($payload),
                'previous_hash' => $previousHash,
                'row_counts' => $rowCounts,
            ]);

            event(new SnapshotTaken($snapshot));

            return $snapshot;
        });
    }
}

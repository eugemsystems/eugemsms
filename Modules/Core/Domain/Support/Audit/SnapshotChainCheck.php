<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Audit;

use Modules\Core\Domain\Contracts\Audit\IntegrityCheck;
use Modules\Core\Domain\DataObjects\Audit\IntegrityCheckResult;
use Modules\Core\Domain\Support\Sessions\CanonicalPayloadHasher;
use Modules\Core\Models\PeriodSnapshot;

/**
 * Book A CORE-08 §4. Reuses CORE-03's `PeriodSnapshot` hash chain
 * (creation-order via `id`, not an explicit sequence column) — same
 * "code owns one hashing implementation, every chain in the platform
 * reuses it" principle as `FinancialAuditChainCheck`.
 */
final class SnapshotChainCheck implements IntegrityCheck
{
    public function checkType(): string
    {
        return 'snapshot_chain';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function run(?int $schoolId): IntegrityCheckResult
    {
        $query = PeriodSnapshot::withoutGlobalScopes()->orderBy('school_id')->orderBy('id');

        if ($schoolId !== null) {
            $query->where('school_id', $schoolId);
        }

        $snapshots = $query->get()->groupBy('school_id');
        $failures = [];
        $checked = 0;

        foreach ($snapshots as $forSchool => $rows) {
            $previousHash = null;

            foreach ($rows as $snapshot) {
                $checked++;

                if ($snapshot->previous_hash !== $previousHash) {
                    $failures[] = ['school_id' => $forSchool, 'snapshot_id' => $snapshot->id, 'reason' => 'previous_hash mismatch'];
                } elseif (CanonicalPayloadHasher::hash($snapshot->payload) !== $snapshot->payload_hash) {
                    $failures[] = ['school_id' => $forSchool, 'snapshot_id' => $snapshot->id, 'reason' => 'payload_hash mismatch — payload was altered'];
                }

                $previousHash = $snapshot->payload_hash;
            }
        }

        return new IntegrityCheckResult(
            status: $failures === [] ? 'passed' : 'failed',
            recordsChecked: $checked,
            failuresFound: count($failures),
            failureDetails: $failures,
        );
    }
}

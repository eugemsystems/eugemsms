<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Audit;

use Modules\Core\Domain\Contracts\Audit\IntegrityCheck;
use Modules\Core\Domain\DataObjects\Audit\IntegrityCheckResult;
use Modules\Core\Domain\Support\Sessions\CanonicalPayloadHasher;
use Modules\Core\Models\FinancialAuditLogEntry;

/**
 * Book A CORE-08 §4/BR-CORE-08-008/AC-CORE-08-002. Verifies every
 * school's `financial_audit_log` chain: sequence is gapless, each
 * row's `previous_hash` matches the prior row's `payload_hash`, and
 * each row's own `payload_hash` still matches its stored payload
 * (catching direct database tampering, not just chain-link breaks).
 */
final class FinancialAuditChainCheck implements IntegrityCheck
{
    public function checkType(): string
    {
        return 'audit_chain';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function run(?int $schoolId): IntegrityCheckResult
    {
        $query = FinancialAuditLogEntry::query()->orderBy('school_id')->orderBy('sequence');

        if ($schoolId !== null) {
            $query->where('school_id', $schoolId);
        }

        $entries = $query->get()->groupBy('school_id');
        $failures = [];
        $checked = 0;

        foreach ($entries as $forSchool => $rows) {
            $previousHash = null;
            $expectedSequence = 1;

            foreach ($rows as $row) {
                $checked++;

                if ($row->sequence !== $expectedSequence) {
                    $failures[] = ['school_id' => $forSchool, 'sequence' => $row->sequence, 'reason' => 'sequence gap'];
                } elseif ($row->previous_hash !== $previousHash) {
                    $failures[] = ['school_id' => $forSchool, 'sequence' => $row->sequence, 'reason' => 'previous_hash mismatch'];
                } elseif (CanonicalPayloadHasher::hash($row->payload) !== $row->payload_hash) {
                    $failures[] = ['school_id' => $forSchool, 'sequence' => $row->sequence, 'reason' => 'payload_hash mismatch — payload was altered'];
                }

                $previousHash = $row->payload_hash;
                $expectedSequence = $row->sequence + 1;
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

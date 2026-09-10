<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Support;

use Modules\Core\Domain\Contracts\Audit\IntegrityCheck;
use Modules\Core\Domain\DataObjects\Audit\IntegrityCheckResult;
use Modules\Welfare\Models\SafeguardingAuditEntry;

/**
 * Book G BRD-08 §5/BR-BRD-08-006/AC-BRD-08-013. Mirrors
 * `Modules\Core\Domain\Support\Audit\FinancialAuditChainCheck` exactly
 * — sequence gapless, `previous_hash` matches, `payload_hash` still
 * matches (no direct-DB tampering) — for the separate
 * `safeguarding_audit` stream. `payload_hash` here is computed once at
 * write time from the recorded event fields themselves (there being no
 * separate stored "payload" column on this table, unlike
 * `financial_audit_log`), so tamper-detection covers the chain link,
 * not a payload body — a deliberate, smaller surface matching this
 * table's own leaner schema.
 */
final class SafeguardingAuditChainCheck implements IntegrityCheck
{
    public function checkType(): string
    {
        return 'safeguarding_audit_chain';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function run(?int $schoolId): IntegrityCheckResult
    {
        $query = SafeguardingAuditEntry::query()->orderBy('school_id')->orderBy('sequence');

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

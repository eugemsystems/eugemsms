<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Sessions\CanonicalPayloadHasher;
use Modules\Core\Models\School;
use Modules\Welfare\Domain\DataObjects\RecordSafeguardingAuditEntryData;
use Modules\Welfare\Models\SafeguardingAuditEntry;

/**
 * ACT-RecordSafeguardingAuditEntry (Book G BRD-08 §2/§3 ⭐⭐/
 * BR-BRD-08-005/006/AC-BRD-08-005). Mirrors
 * `Modules\Core\Domain\Actions\Audit\RecordFinancialAuditEntryAction`
 * exactly — same per-school sequence lock via the `schools` row, same
 * canonical-payload hash chain — because this stream needs the
 * identical tamper-evidence property, just kept SEPARATE from
 * `financial_audit_log`/`data_access_log` per BR-BRD-08-006. Every
 * read of a case, entry, counselling note or concern calls this —
 * reads, not just writes.
 */
final class RecordSafeguardingAuditEntryAction extends Action
{
    public function execute(RecordSafeguardingAuditEntryData $data): SafeguardingAuditEntry
    {
        return $this->transaction(function () use ($data): SafeguardingAuditEntry {
            School::query()->whereKey($data->schoolId)->lockForUpdate()->firstOrFail();

            $previous = SafeguardingAuditEntry::query()
                ->where('school_id', $data->schoolId)
                ->orderByDesc('sequence')
                ->first();

            $payloadHash = CanonicalPayloadHasher::hash($data->payload);

            return SafeguardingAuditEntry::create([
                'school_id' => $data->schoolId,
                'sequence' => ($previous !== null ? $previous->sequence : 0) + 1,
                'event_type' => $data->eventType,
                'case_id' => $data->caseId,
                'concern_id' => $data->concernId,
                'user_id' => $data->userId,
                'user_role_at_time' => $data->userRoleAtTime,
                'access_basis' => $data->accessBasis,
                'ip_address' => $data->ip,
                'user_agent' => $data->userAgent,
                'payload_hash' => $payloadHash,
                'previous_hash' => $previous !== null ? $previous->payload_hash : null,
                'occurred_at' => Carbon::now(),
            ]);
        });
    }
}

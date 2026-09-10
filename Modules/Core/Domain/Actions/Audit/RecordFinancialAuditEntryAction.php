<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Audit;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Audit\RecordFinancialAuditEntryData;
use Modules\Core\Domain\Support\Sessions\CanonicalPayloadHasher;
use Modules\Core\Models\FinancialAuditLogEntry;
use Modules\Core\Models\School;

/**
 * ACT-RecordFinancialAuditEntry (Book A CORE-08 §2/BR-CORE-08-005..007
 * /014/AC-CORE-08-001). Never queued — this commits synchronously,
 * inside the SAME transaction as the financial event it records, so
 * the two either both land or both roll back. Sequence allocation
 * locks the `schools` row itself as the per-school mutex (rather than
 * `lockForUpdate()` on the last `financial_audit_log` row, which finds
 * nothing to lock — and so provides no exclusion at all — the first
 * time a school ever gets an entry).
 */
final class RecordFinancialAuditEntryAction extends Action
{
    public function execute(RecordFinancialAuditEntryData $data): FinancialAuditLogEntry
    {
        return $this->transaction(function () use ($data): FinancialAuditLogEntry {
            School::query()->whereKey($data->schoolId)->lockForUpdate()->firstOrFail();

            $previous = FinancialAuditLogEntry::query()
                ->where('school_id', $data->schoolId)
                ->orderByDesc('sequence')
                ->first();

            $payloadHash = CanonicalPayloadHasher::hash($data->payload);

            return FinancialAuditLogEntry::create([
                'school_id' => $data->schoolId,
                'sequence' => ($previous !== null ? $previous->sequence : 0) + 1,
                'event_type' => $data->eventType,
                'subject_type' => $data->subjectType,
                'subject_id' => $data->subjectId,
                'amount_minor' => $data->amount?->minor,
                'amount_currency' => $data->amount?->currency->value,
                'payload' => $data->payload,
                'payload_hash' => $payloadHash,
                'previous_hash' => $previous !== null ? $previous->payload_hash : null,
                'causer_id' => $data->causerId,
                'impersonator_id' => $data->impersonatorId,
                'academic_year_id' => $data->academicYearId,
                'term_id' => $data->termId,
                'ip_address' => $data->ip,
                'occurred_at' => Carbon::now(),
            ]);
        });
    }
}

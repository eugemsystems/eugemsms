<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Welfare\Domain\DataObjects\RecordCaseEntryData;
use Modules\Welfare\Domain\DataObjects\RecordSafeguardingAuditEntryData;
use Modules\Welfare\Models\CaseEntry;

/**
 * ACT-RecordCaseEntry (Book G BRD-08 §2/BR-BRD-08-005/008). Append-only
 * — see `CaseEntry`'s own guard for corrections. Writing an entry is
 * audited the same way reading one is (BR-BRD-08-005 says "every
 * read", but a write is trivially also visible to its author, so this
 * pass logs it too for a complete chronology of who touched the case
 * and when).
 */
final class RecordCaseEntryAction extends Action
{
    public function __construct(
        private readonly RecordSafeguardingAuditEntryAction $recordAudit,
    ) {}

    public function execute(RecordCaseEntryData $data): CaseEntry
    {
        return $this->transaction(function () use ($data): CaseEntry {
            $entry = CaseEntry::create([
                'school_id' => $data->schoolId,
                'case_id' => $data->caseId,
                'entry_type' => $data->entryType,
                'entry_at' => $data->entryAt,
                'recorded_at' => Carbon::now(),
                'content' => $data->content,
                'is_learner_account' => $data->isLearnerAccount,
                'present_persons' => $data->presentPersons,
                'recorded_by' => $data->recordedByUserId,
                'attachment_file_ids' => $data->attachmentFileIds,
            ]);

            $this->recordAudit->execute(new RecordSafeguardingAuditEntryData(
                schoolId: $data->schoolId,
                eventType: 'entry_written',
                userId: $data->recordedByUserId,
                userRoleAtTime: 'case_contributor',
                payload: ['case_id' => $data->caseId, 'entry_id' => $entry->id],
                caseId: $data->caseId,
            ));

            return $entry;
        });
    }
}

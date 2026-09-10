<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Audit\RecordDataAccessAction;
use Modules\Core\Domain\DataObjects\Audit\RecordDataAccessData;
use Modules\People\Domain\DataObjects\ViewDisciplinaryCaseData;
use Modules\People\Models\StaffDisciplinaryCase;

/**
 * ACT-ViewDisciplinaryCase (Book C PPL-04 §4/BR-PPL-04-020 ⭐). "Every
 * access writes to data_access_log" — this Action is the ONLY
 * sanctioned way to read one of these rows for exactly that reason;
 * reading `StaffDisciplinaryCase::find()` directly elsewhere bypasses
 * the audit trail this rule requires. Who is authorised to call this
 * at all (head, deputy, case handler) is left to the caller's own
 * permission check, matching this codebase's established convention.
 */
final class ViewDisciplinaryCaseAction extends Action
{
    public function __construct(
        private readonly RecordDataAccessAction $recordDataAccess,
    ) {}

    public function execute(ViewDisciplinaryCaseData $data): StaffDisciplinaryCase
    {
        $case = StaffDisciplinaryCase::findOrFail($data->caseId);

        $this->recordDataAccess->execute(new RecordDataAccessData(
            schoolId: $case->school_id,
            userId: $data->viewedByUserId,
            accessType: 'view',
            resourceType: 'staff_disciplinary_case',
            resourceId: $case->id,
            ip: $data->ip,
        ));

        return $case;
    }
}

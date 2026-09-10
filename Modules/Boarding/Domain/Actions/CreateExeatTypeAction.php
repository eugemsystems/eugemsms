<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use Modules\Boarding\Domain\DataObjects\CreateExeatTypeData;
use Modules\Boarding\Models\ExeatType;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-CreateExeatType (Book F BRD-03 §2).
 */
final class CreateExeatTypeAction extends Action
{
    public function execute(CreateExeatTypeData $data): ExeatType
    {
        return $this->transaction(fn (): ExeatType => ExeatType::create([
            'school_id' => $data->schoolId,
            'code' => $data->code,
            'name' => $data->name,
            'max_duration_hours' => $data->maxDurationHours,
            'requires_guardian_request' => $data->requiresGuardianRequest,
            'requires_document' => $data->requiresDocument,
            'min_notice_hours' => $data->minNoticeHours,
            'allowed_per_term' => $data->allowedPerTerm,
            'counts_toward_quota' => $data->countsTowardQuota,
            'blocks_on_fee_arrears' => $data->blocksOnFeeArrears,
            'blocks_on_suspension' => $data->blocksOnSuspension,
            'is_active' => true,
        ]));
    }
}

<?php

declare(strict_types=1);

namespace Modules\Sport\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Sport\Domain\DataObjects\IssueEquipmentData;
use Modules\Sport\Models\EquipmentIssue;

/**
 * ACT-IssueEquipment (Book H2 OPS-07 §3/BR-OPS-07-011). See
 * `equipment_issues`'s own migration docblock for why this is a new,
 * additive table rather than a reuse of `Modules\Stores`'s
 * staff-only `ChangeAssetCustodianAction`.
 */
final class IssueEquipmentAction extends Action
{
    public function execute(IssueEquipmentData $data): EquipmentIssue
    {
        return $this->transaction(fn (): EquipmentIssue => EquipmentIssue::create([
            'school_id' => $data->schoolId,
            'activity_id' => $data->activityId,
            'asset_id' => $data->assetId,
            'student_id' => $data->studentId,
            'issued_at' => Carbon::now(),
            'issued_by' => $data->issuedByUserId,
            'expected_return_on' => $data->expectedReturnOn?->toDateString(),
            'notes' => $data->notes,
        ]));
    }
}

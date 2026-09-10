<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\People\Domain\DataObjects\LinkGuardianToStudentData;
use Modules\People\Domain\Events\GuardianLinked;
use Modules\People\Models\StudentGuardian;

/**
 * ACT-LinkGuardianToStudent (Book C PPL-03 §3 ⭐/BR-PPL-03-005). Every
 * right named on the DTO is granted explicitly here — nothing is
 * inferred from `relationship`, which is descriptive only.
 */
final class LinkGuardianToStudentAction extends Action
{
    public function execute(LinkGuardianToStudentData $data): StudentGuardian
    {
        return $this->transaction(function () use ($data): StudentGuardian {
            $link = StudentGuardian::create([
                'student_id' => $data->studentId,
                'guardian_id' => $data->guardianId,
                'relationship' => $data->relationship,
                'is_primary_contact' => $data->isPrimaryContact,
                'is_emergency_contact' => $data->isEmergencyContact,
                'is_fee_responsible' => $data->isFeeResponsible,
                'may_collect_learner' => $data->mayCollectLearner,
                'may_view_full_balance' => $data->mayViewFullBalance,
                'has_court_restriction' => $data->hasCourtRestriction,
                'status' => 'active',
                'effective_from' => ($data->effectiveFrom ?? Carbon::now())->toDateString(),
                'created_by' => $data->createdByUserId,
            ]);

            event(new GuardianLinked($link));

            return $link;
        });
    }
}

<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use InvalidArgumentException;
use Modules\Boarding\Domain\DataObjects\CreateLearnerIncompatibilityData;
use Modules\Boarding\Models\LearnerIncompatibility;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-CreateLearnerIncompatibility (Book F BRD-01 §2/§4/BR-BRD-01-008).
 * Gap-filling, admin-UI pass: no Action anywhere ever created a
 * `learner_incompatibilities` row before this (verified: `grep -rn
 * "LearnerIncompatibility::create" Modules/Boarding` returns nothing
 * outside this file) — every existing row came from
 * `TenantModelRegistry`'s own factory call. The reason is confidential
 * by default (BR-BRD-01-008); the housemaster's screen honours the
 * constraint without ever reading `reason` for a row flagged
 * confidential — that visibility rule is enforced in the Livewire
 * layer (`Allocation\Incompatibilities`), not here, matching this
 * codebase's established "absent, not merely hidden" tiered-visibility
 * pattern for medical/safeguarding fields elsewhere.
 */
final class CreateLearnerIncompatibilityAction extends Action
{
    public function execute(CreateLearnerIncompatibilityData $data): LearnerIncompatibility
    {
        if ($data->studentAId === $data->studentBId) {
            throw new InvalidArgumentException('A learner cannot be marked incompatible with themselves.');
        }

        [$studentAId, $studentBId] = $data->studentAId < $data->studentBId
            ? [$data->studentAId, $data->studentBId]
            : [$data->studentBId, $data->studentAId];

        return $this->transaction(fn (): LearnerIncompatibility => LearnerIncompatibility::create([
            'school_id' => $data->schoolId,
            'student_a_id' => $studentAId,
            'student_b_id' => $studentBId,
            'scope' => $data->scope,
            'reason_category' => $data->reasonCategory,
            'reason' => $data->reason,
            'is_confidential' => $data->isConfidential,
            'raised_by' => $data->raisedByUserId,
            'expires_on' => $data->expiresOn?->toDateString(),
            'is_active' => true,
        ]));
    }
}

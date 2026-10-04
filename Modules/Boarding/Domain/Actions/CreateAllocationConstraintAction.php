<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use Modules\Boarding\Domain\DataObjects\CreateAllocationConstraintData;
use Modules\Boarding\Models\AllocationConstraint;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-CreateAllocationConstraint (Book F BRD-01 §2/§3 ⭐). Gap-filling,
 * admin-UI pass: `allocation_constraints` had a model, migration, and
 * factory, but no Action anywhere ever created one (verified: `grep
 * -rn "AllocationConstraint::create" Modules/Boarding` returns nothing
 * outside this file) — every existing row came from
 * `TenantModelRegistry`'s own factory call. This never accepts
 * `constraint_type = 'gender_match'` as something a user can disable:
 * the gender hard constraint (BR-BRD-01-001) is enforced unconditionally
 * in `AllocateBedAction`/`MoveLearnerAction`/`RunBulkAllocationAction`
 * themselves, never read from this configurable table — so this screen
 * has no path to weaken it, by construction.
 */
final class CreateAllocationConstraintAction extends Action
{
    public function execute(CreateAllocationConstraintData $data): AllocationConstraint
    {
        return $this->transaction(fn (): AllocationConstraint => AllocationConstraint::create([
            'school_id' => $data->schoolId,
            'constraint_type' => $data->constraintType,
            'severity' => $data->severity,
            'weight' => $data->weight,
            'hostel_id' => $data->hostelId,
            'grade_level_ids' => $data->gradeLevelIds,
            'value' => $data->value,
            'reason' => $data->reason,
            'is_active' => true,
        ]));
    }
}

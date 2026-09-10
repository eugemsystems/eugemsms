<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use Modules\Boarding\Domain\DataObjects\RecordDietaryRequirementData;
use Modules\Boarding\Domain\Events\DietaryRequirementAdded;
use Modules\Boarding\Domain\Events\LifeThreateningAllergyFlagged;
use Modules\Boarding\Models\DietaryRequirement;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-RecordDietaryRequirement (Book F BRD-04 §4/BR-BRD-04-009/010 ⭐).
 * A requirement sourced from a medical record requires nurse
 * verification before it is treated as clinical
 * (`VerifyDietaryRequirementAction`) — religious/ethical ones do not.
 * Recording one directly never marks it verified.
 */
final class RecordDietaryRequirementAction extends Action
{
    public function execute(RecordDietaryRequirementData $data): DietaryRequirement
    {
        return $this->transaction(function () use ($data): DietaryRequirement {
            $requirement = DietaryRequirement::create([
                'school_id' => $data->schoolId,
                'student_id' => $data->studentId,
                'requirement_type' => $data->requirementType,
                'severity' => $data->severity,
                'allergens' => $data->allergens,
                'excluded_items' => $data->excludedItems,
                'description' => $data->description,
                'alternative_provision' => $data->alternativeProvision,
                'medical_source_id' => $data->medicalSourceId,
                'requires_epipen' => $data->requiresEpipen,
                'verified_by_nurse' => false,
                'effective_from' => $data->effectiveFrom,
                'is_active' => true,
            ]);

            event(new DietaryRequirementAdded($requirement));

            if ($data->severity === 'life_threatening') {
                event(new LifeThreateningAllergyFlagged($requirement));
            }

            return $requirement;
        });
    }
}

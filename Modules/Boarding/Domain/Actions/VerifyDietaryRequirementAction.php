<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Boarding\Domain\DataObjects\VerifyDietaryRequirementData;
use Modules\Boarding\Models\DietaryRequirement;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-VerifyDietaryRequirement (Book F BRD-04 §4/BR-BRD-04-010).
 */
final class VerifyDietaryRequirementAction extends Action
{
    public function execute(VerifyDietaryRequirementData $data): DietaryRequirement
    {
        $requirement = DietaryRequirement::findOrFail($data->dietaryRequirementId);

        return $this->transaction(fn (): DietaryRequirement => tap($requirement)->update([
            'verified_by_nurse' => true,
            'verified_at' => Carbon::now(),
        ]));
    }
}

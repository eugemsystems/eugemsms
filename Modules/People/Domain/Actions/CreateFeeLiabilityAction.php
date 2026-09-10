<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\People\Domain\DataObjects\CreateFeeLiabilityData;
use Modules\People\Domain\Events\LiabilityChanged;
use Modules\People\Models\FeeLiability;

/**
 * ACT-CreateFeeLiability (Book C PPL-03 §3/BR-PPL-03-010). Applies
 * from `effective_from` forward only — never retrospective. The
 * "shares must total 100%" live check the liability designer screen
 * would run is deferred along with that screen; `LiabilityResolver`
 * itself tolerates (and correctly resolves) a genuinely incomplete
 * percentage split by falling the residue through to pass 3.
 */
final class CreateFeeLiabilityAction extends Action
{
    public function execute(CreateFeeLiabilityData $data): FeeLiability
    {
        return $this->transaction(function () use ($data): FeeLiability {
            $liability = FeeLiability::create([
                'school_id' => $data->schoolId,
                'student_id' => $data->studentId,
                'guardian_id' => $data->guardianId,
                'component_id' => $data->componentId,
                'share_type' => $data->shareType,
                'share_percent' => $data->sharePercent,
                'share_amount_minor' => $data->shareAmountMinor,
                'currency' => $data->currency,
                'priority' => $data->priority,
                'effective_from' => ($data->effectiveFrom ?? Carbon::now())->toDateString(),
                'is_active' => true,
                'created_by' => $data->createdByUserId,
            ]);

            event(new LiabilityChanged($liability));

            return $liability;
        });
    }
}

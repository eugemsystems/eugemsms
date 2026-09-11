<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Domain\Events\SponsorAwardLinked;
use Modules\Finance\Domain\Exceptions\SponsorAwardMissingGranterException;
use Modules\Finance\Models\DiscountAward;
use Modules\People\Domain\Actions\CreateFeeLiabilityAction;
use Modules\People\Domain\DataObjects\CreateFeeLiabilityData;

/**
 * ACT-ActivateSponsorAwardLiability (Book K FIN-07 §4/BR-FIN-07-010 ⭐
 * (AC-FIN-07-004)). A sponsor-funded award never posts a discount — it
 * creates or updates a `PPL-03` `FeeLiability` row against the
 * sponsor, through the existing `CreateFeeLiabilityAction` (this
 * module owns no second liability-routing mechanism), so the
 * sponsoring organisation is billed the full amount and the school's
 * income is completely unaffected; only the payer changes. Called at
 * the moment the award actually takes effect — immediately from
 * `GrantAwardAction` when no approval is required, or from
 * `DiscountAward::onApproved()` once `CORE-07` approves it.
 */
final class ActivateSponsorAwardLiabilityAction extends Action
{
    public function __construct(
        private readonly CreateFeeLiabilityAction $createFeeLiability,
    ) {}

    public function execute(DiscountAward $award): void
    {
        if (! $award->isSponsorFunded()) {
            return;
        }

        if ($award->granted_by === null) {
            throw new SponsorAwardMissingGranterException(
                "Sponsor-funded award [{$award->id}] has no granting user — a sponsor award is never system-automatic."
            );
        }

        $this->transaction(function () use ($award): void {
            $componentIds = $award->applies_to_components ?? [null];

            foreach ($componentIds as $componentId) {
                $this->createFeeLiability->execute(new CreateFeeLiabilityData(
                    schoolId: $award->school_id,
                    studentId: $award->student_id,
                    guardianId: $award->sponsor_guardian_id,
                    shareType: 'full_component',
                    createdByUserId: $award->granted_by,
                    componentId: $componentId,
                    priority: 10,
                    effectiveFrom: $award->effective_from,
                ));
            }

            event(new SponsorAwardLinked($award));
        });
    }
}

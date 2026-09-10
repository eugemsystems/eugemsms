<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Models\Term;
use Modules\Finance\Domain\Actions\CreateAdHocChargeAction;
use Modules\Finance\Domain\DataObjects\CreateAdHocChargeData;
use Modules\Welfare\Models\ExternalReferral;

/**
 * ACT-ChargeReferralCost (Book G BRD-06 §4/BR-BRD-06-024). Only ever
 * charges the guardian when the referral itself says the cost is
 * borne by the guardian — a school-borne referral is never charged.
 */
final class ChargeReferralCostAction extends Action
{
    public function __construct(
        private readonly CreateAdHocChargeAction $createAdHocCharge,
    ) {}

    public function execute(int $referralId, int $termId, int $academicYearId, int $feeComponentId, int $costMinor, string $currency, int $approvedByUserId): ExternalReferral
    {
        $referral = ExternalReferral::findOrFail($referralId);

        if ($referral->cost_borne_by !== 'guardian') {
            throw new InvalidStateTransitionException(
                "Referral #{$referral->id} cost is not guardian-borne — refusing to raise a charge.",
                ['referral_id' => $referral->id, 'cost_borne_by' => $referral->cost_borne_by],
            );
        }

        Term::findOrFail($termId);

        return $this->transaction(function () use ($referral, $termId, $academicYearId, $feeComponentId, $costMinor, $currency, $approvedByUserId): ExternalReferral {
            $charge = $this->createAdHocCharge->execute(new CreateAdHocChargeData(
                schoolId: $referral->school_id,
                academicYearId: $academicYearId,
                termId: $termId,
                studentId: $referral->student_id,
                componentId: $feeComponentId,
                description: "External referral: {$referral->facility_name}",
                unitRateMinor: $costMinor,
                currency: $currency,
                raisedByUserId: $approvedByUserId,
                sourceType: 'external_referral',
                sourceId: $referral->id,
                approvedByUserId: $approvedByUserId,
            ));

            return tap($referral)->update([
                'cost_minor' => $costMinor,
                'ad_hoc_charge_id' => $charge->id,
            ]);
        });
    }
}

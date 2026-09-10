<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use Modules\Boarding\Domain\DataObjects\ApproveHostelDamageChargeData;
use Modules\Boarding\Domain\Events\DamageChargeApproved;
use Modules\Boarding\Models\HostelDamage;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Models\Term;
use Modules\Finance\Domain\Actions\CreateAdHocChargeAction;
use Modules\Finance\Domain\DataObjects\CreateAdHocChargeData;

/**
 * ACT-ApproveHostelDamageCharge (Book F BRD-01 §4/BR-BRD-01-012 ⭐/
 * AC-BRD-01-005). Damage is never charged silently — this is the only
 * path from a reported damage to a `FIN-02` ad hoc charge, and it
 * always requires this action's own approval step (the caller's
 * `approvedByUserId`, matching `CreateAdHocChargeAction`'s own
 * approval-threshold gate). Shared liability splits the assessed cost
 * across `liable_student_ids` using a largest-remainder allocation so
 * the charges sum to EXACTLY the assessed cost, in integer minor
 * units, with no rounding drift.
 */
final class ApproveHostelDamageChargeAction extends Action
{
    public function __construct(
        private readonly CreateAdHocChargeAction $createAdHocCharge,
    ) {}

    public function execute(ApproveHostelDamageChargeData $data): HostelDamage
    {
        $damage = HostelDamage::findOrFail($data->damageId);

        if ($damage->charge_status !== 'pending') {
            throw new InvalidStateTransitionException(
                "Damage #{$damage->id} must be pending to be approved (currently {$damage->charge_status}).",
                ['damage_id' => $damage->id, 'charge_status' => $damage->charge_status],
            );
        }

        $liableStudentIds = $damage->liable_student_ids ?? [];
        $term = Term::findOrFail($damage->term_id);

        return $this->transaction(function () use ($damage, $data, $liableStudentIds, $term): HostelDamage {
            $chargeIds = [];

            if ($liableStudentIds !== []) {
                $count = count($liableStudentIds);
                $base = intdiv($data->actualCostMinor, $count);
                $remainder = $data->actualCostMinor % $count;

                foreach (array_values($liableStudentIds) as $index => $studentId) {
                    $amount = $base + ($index < $remainder ? 1 : 0);

                    $charge = $this->createAdHocCharge->execute(new CreateAdHocChargeData(
                        schoolId: $damage->school_id,
                        academicYearId: $term->academic_year_id,
                        termId: $damage->term_id,
                        studentId: $studentId,
                        componentId: $data->feeComponentId,
                        description: "Boarding damage: {$damage->damage_type} — {$damage->description}",
                        unitRateMinor: $amount,
                        currency: $damage->currency,
                        raisedByUserId: $data->approvedByUserId,
                        sourceType: 'hostel_damage',
                        sourceId: $damage->id,
                        approvedByUserId: $data->approvedByUserId,
                    ));

                    $chargeIds[] = $charge->id;
                }
            }

            $damage->update([
                'actual_cost_minor' => $data->actualCostMinor,
                'ad_hoc_charge_ids' => $chargeIds,
                'charge_status' => 'charged',
            ]);

            event(new DamageChargeApproved($damage));

            return $damage;
        });
    }
}

<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\Actions;

use Illuminate\Support\Collection;
use Modules\Compliance\Domain\DataObjects\BillZimsecEntryFeesData;
use Modules\Compliance\Models\ZimsecCandidate;
use Modules\Compliance\Models\ZimsecRegistration;
use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Domain\Actions\CreateAdHocChargeAction;
use Modules\Finance\Domain\DataObjects\CreateAdHocChargeData;

/**
 * ACT-BillZimsecEntryFees (Book H3 CMP-01 §3/BR-CMP-01-006). Raises
 * one real `ad_hoc_charges` row per validated candidate not yet billed,
 * through the actual Book B FIN-02 `CreateAdHocChargeAction` — the
 * genuine cross-module reuse this rule calls for. `zimsec_candidates.ad_hoc_charge_id`
 * is what this codebase can honestly link to; see the module's own
 * scope note on why that stops short of "appears on the guardian's
 * invoice" (FIN-02 never invoices an ad hoc charge anywhere yet).
 */
final class BillZimsecEntryFeesAction extends Action
{
    public function __construct(
        private readonly CreateAdHocChargeAction $createAdHocCharge,
    ) {}

    /**
     * @return Collection<int, ZimsecCandidate>
     */
    public function execute(BillZimsecEntryFeesData $data): Collection
    {
        $registration = ZimsecRegistration::findOrFail($data->registrationId);

        return $this->transaction(function () use ($registration, $data): Collection {
            $billed = new Collection;

            $candidates = ZimsecCandidate::where('registration_id', $registration->id)
                ->whereNull('ad_hoc_charge_id')
                ->get();

            foreach ($candidates as $candidate) {
                $charge = $this->createAdHocCharge->execute(new CreateAdHocChargeData(
                    schoolId: $registration->school_id,
                    academicYearId: $data->academicYearId,
                    termId: $data->termId,
                    studentId: $candidate->student_id,
                    componentId: $data->feeComponentId,
                    description: "ZIMSEC {$registration->exam_level} entry fee — {$registration->exam_series}",
                    unitRateMinor: $candidate->entry_fee_minor,
                    currency: $candidate->currency,
                    raisedByUserId: $data->raisedByUserId,
                    sourceType: 'zimsec_candidate',
                    sourceId: $candidate->id,
                    approvedByUserId: $data->approvedByUserId,
                ));

                $candidate->update(['ad_hoc_charge_id' => $charge->id]);
                $billed->push($candidate);
            }

            $registration->update([
                'total_fees_minor' => ZimsecCandidate::where('registration_id', $registration->id)->sum('entry_fee_minor'),
            ]);

            return $billed;
        });
    }
}

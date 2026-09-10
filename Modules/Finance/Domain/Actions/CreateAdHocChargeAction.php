<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Finance\Domain\DataObjects\CreateAdHocChargeData;
use Modules\Finance\Domain\Events\AdHocChargeRaised;
use Modules\Finance\Domain\Exceptions\AdHocChargeRequiresApprovalException;
use Modules\Finance\Models\AdHocCharge;

/**
 * ACT-CreateAdHocCharge (Book B FIN-02 §2/§6/BR-FIN-02-019). A one-off
 * charge above `finance.ad_hoc_approval_threshold_minor` is refused
 * without an approving user — there is no CORE-07 approval-chain
 * wiring here yet (the same documented deferral `ACA-02`'s
 * `SubjectChangeCutoffPolicy` makes for its own approval gate); the
 * caller re-submits with `approvedByUserId` set once a human has
 * approved it out of band.
 */
final class CreateAdHocChargeAction extends Action
{
    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    public function execute(CreateAdHocChargeData $data): AdHocCharge
    {
        $amountMinor = (int) round($data->unitRateMinor * (float) $data->quantity);
        $threshold = (int) $this->settings->get('finance.ad_hoc_approval_threshold_minor', new ScopeChain(schoolId: $data->schoolId));

        if ($amountMinor > $threshold && $data->approvedByUserId === null) {
            throw AdHocChargeRequiresApprovalException::aboveThreshold($amountMinor, $threshold);
        }

        return $this->transaction(function () use ($data, $amountMinor): AdHocCharge {
            $charge = AdHocCharge::create([
                'school_id' => $data->schoolId,
                'academic_year_id' => $data->academicYearId,
                'term_id' => $data->termId,
                'student_id' => $data->studentId,
                'component_id' => $data->componentId,
                'description' => $data->description,
                'quantity' => $data->quantity,
                'unit_rate_minor' => $data->unitRateMinor,
                'amount_minor' => $amountMinor,
                'currency' => $data->currency,
                'source_type' => $data->sourceType,
                'source_id' => $data->sourceId,
                'status' => 'pending',
                'raised_by' => $data->raisedByUserId,
                'approved_by' => $data->approvedByUserId,
                'created_at' => Carbon::now(),
            ]);

            event(new AdHocChargeRaised($charge));

            return $charge;
        });
    }
}

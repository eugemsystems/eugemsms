<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Approvals\RequestApprovalAction;
use Modules\Core\Domain\DataObjects\Approvals\RequestApprovalData;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Finance\Domain\DataObjects\GrantAwardData;
use Modules\Finance\Domain\Events\AwardGranted;
use Modules\Finance\Domain\Exceptions\ApplicationNotApprovedException;
use Modules\Finance\Domain\Exceptions\ApplicationRequiredException;
use Modules\Finance\Models\DiscountAward;
use Modules\Finance\Models\DiscountScheme;
use Modules\Finance\Models\ScholarshipApplication;

/**
 * ACT-GrantAward (Book K FIN-07 §2/§4/BR-FIN-07-005/008/010 ⭐).
 * Application-based schemes refuse a standalone grant (BR-FIN-07-005);
 * a scheme or amount requiring approval routes through `CORE-07`
 * (BR-FIN-07-008) and the award sits `pending_approval` until
 * `DiscountAward::onApproved()` activates it — including its sponsor
 * liability, if any (BR-FIN-07-010). Otherwise it's active
 * immediately.
 */
final class GrantAwardAction extends Action
{
    public function __construct(
        private readonly SettingResolver $settings,
        private readonly RequestApprovalAction $requestApproval,
        private readonly ActivateSponsorAwardLiabilityAction $activateSponsorLiability,
    ) {}

    public function execute(GrantAwardData $data): DiscountAward
    {
        $scheme = DiscountScheme::query()->findOrFail($data->schemeId);

        if ($scheme->scheme_type === 'application_based') {
            if ($data->applicationId === null) {
                throw new ApplicationRequiredException(
                    "Scheme [{$scheme->code}] requires a submitted scholarship application before an award can be granted."
                );
            }

            $application = ScholarshipApplication::query()->findOrFail($data->applicationId);

            if ($application->status !== 'approved') {
                throw new ApplicationNotApprovedException(
                    "Application [{$application->id}] has not been approved — its current status is [{$application->status}]."
                );
            }
        }

        $threshold = (int) $this->settings->get('finance.award_approval_threshold_minor', new ScopeChain(schoolId: $data->schoolId));
        $exceedsThreshold = $data->awardMethod === 'fixed_amount' && ($data->awardAmountMinor ?? 0) >= $threshold;
        $requiresApproval = $scheme->requires_approval || $exceedsThreshold;

        return $this->transaction(function () use ($scheme, $data, $requiresApproval): DiscountAward {
            $award = DiscountAward::create([
                'school_id' => $data->schoolId,
                'scheme_id' => $scheme->id,
                'student_id' => $data->studentId,
                'application_id' => $data->applicationId,
                'academic_year_id' => $data->academicYearId,
                'term_id' => $data->termId,
                'applies_to_components' => $data->appliesToComponents,
                'award_method' => $data->awardMethod,
                'award_percent' => $data->awardPercent,
                'award_amount_minor' => $data->awardAmountMinor,
                'currency' => $data->currency,
                'sponsor_guardian_id' => $data->sponsorGuardianId,
                'effective_from' => $data->effectiveFrom->toDateString(),
                'status' => $requiresApproval ? 'pending_approval' : 'active',
                'condition_note' => $data->conditionNote,
                'granted_by' => $data->grantedByUserId,
            ]);

            if ($requiresApproval) {
                $request = $this->requestApproval->execute(new RequestApprovalData(
                    approvable: $award,
                    schoolId: $data->schoolId,
                    academicYearId: $data->academicYearId,
                    requestedByUserId: $data->grantedByUserId,
                    termId: $data->termId,
                ));

                $award->update(['approval_request_id' => $request->id]);
            } elseif ($award->isSponsorFunded()) {
                $this->activateSponsorLiability->execute($award);
            }

            event(new AwardGranted($award->fresh()));

            return $award->fresh();
        });
    }
}

<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Approvals\RequestApprovalAction;
use Modules\Core\Domain\DataObjects\Approvals\RequestApprovalData;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\Term;
use Modules\Finance\Domain\DataObjects\GrantAwardData;
use Modules\Finance\Domain\Events\AwardGranted;
use Modules\Finance\Domain\Exceptions\ApplicationNotApprovedException;
use Modules\Finance\Domain\Exceptions\ApplicationRequiredException;
use Modules\Finance\Domain\Exceptions\AwardBudgetEnvelopeExceededException;
use Modules\Finance\Models\DiscountAward;
use Modules\Finance\Models\DiscountScheme;
use Modules\Finance\Models\ScholarshipApplication;
use Modules\People\Models\Guardian;
use Modules\People\Models\Student;

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
        private readonly PreviewAwardEnvelopeAction $envelopePreview,
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

        $this->assertValid($scheme, $data);

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

    private function assertValid(DiscountScheme $scheme, GrantAwardData $data): void
    {
        if ($scheme->school_id !== $data->schoolId || ! $scheme->is_active) {
            throw new InvalidArgumentException('That scheme is not available at this school.');
        }

        Student::query()->where('school_id', $data->schoolId)->findOrFail($data->studentId);
        AcademicYear::query()->where('school_id', $data->schoolId)->findOrFail($data->academicYearId);

        if ($data->termId !== null) {
            Term::query()->where('school_id', $data->schoolId)->where('academic_year_id', $data->academicYearId)->findOrFail($data->termId);
        }

        if ($data->applicationId !== null) {
            ScholarshipApplication::query()->where('scheme_id', $scheme->id)->where('student_id', $data->studentId)->findOrFail($data->applicationId);
        }

        if ($data->awardMethod === 'percentage') {
            if ($data->awardPercent === null || ! is_numeric($data->awardPercent) || (float) $data->awardPercent <= 0 || (float) $data->awardPercent > 100) {
                throw new InvalidArgumentException('A percentage award needs a figure between 0 and 100.');
            }
        } elseif ($data->awardMethod === 'fixed_amount') {
            if ($data->awardAmountMinor === null || $data->awardAmountMinor <= 0 || $data->currency === null) {
                throw new InvalidArgumentException('A fixed award needs a positive amount and a currency.');
            }
        } else {
            throw new InvalidArgumentException('An award is either a percentage or a fixed amount.');
        }

        if ($scheme->is_sponsor_funded !== ($data->sponsorGuardianId !== null)) {
            throw new InvalidArgumentException($scheme->is_sponsor_funded ? 'A sponsor-funded scheme needs the sponsoring guardian or organisation.' : 'Only a sponsor-funded scheme takes a sponsor.');
        }

        if ($data->sponsorGuardianId !== null) {
            Guardian::query()->where('school_id', $data->schoolId)->findOrFail($data->sponsorGuardianId);
        }

        $duplicate = DiscountAward::query()->where('school_id', $data->schoolId)->where('scheme_id', $scheme->id)
            ->where('student_id', $data->studentId)->where('academic_year_id', $data->academicYearId)
            ->where('term_id', $data->termId)->whereNotIn('status', ['revoked', 'ended'])->exists();

        if ($duplicate) {
            throw new InvalidArgumentException('This learner already holds that award for the period.');
        }

        // BR-FIN-07-009: a capped envelope refuses an award it cannot cover, naming the shortfall.
        if ($data->awardMethod === 'fixed_amount' && ! $scheme->is_sponsor_funded) {
            $preview = $this->envelopePreview->execute($data->schoolId, $scheme->id, $data->academicYearId, $data->awardAmountMinor);

            if ($preview['capped'] && $preview['currency'] !== $data->currency) {
                throw new InvalidArgumentException("This scheme's envelope is in {$preview['currency']}; grant in that currency.");
            }

            if ($preview['shortfall_minor'] > 0) {
                throw new AwardBudgetEnvelopeExceededException(
                    "This award would exceed the scheme's budget envelope by {$preview['shortfall_minor']} minor units; raise the budget or use a different scheme (BR-FIN-07-009).",
                );
            }
        }
    }
}

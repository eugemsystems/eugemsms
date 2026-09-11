<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Domain\DataObjects\SubmitScholarshipApplicationData;
use Modules\Finance\Domain\Events\ApplicationSubmitted;
use Modules\Finance\Domain\Exceptions\SchemeNotApplicationBasedException;
use Modules\Finance\Models\DiscountScheme;
use Modules\Finance\Models\ScholarshipApplication;

/**
 * ACT-SubmitScholarshipApplication (Book K FIN-07 §2/§4/BR-FIN-07-005/006).
 * `GrantAwardAction` refuses an application-based scheme's award
 * without one of these behind it.
 */
final class SubmitScholarshipApplicationAction extends Action
{
    public function execute(SubmitScholarshipApplicationData $data): ScholarshipApplication
    {
        $scheme = DiscountScheme::query()->findOrFail($data->schemeId);

        if ($scheme->scheme_type !== 'application_based') {
            throw new SchemeNotApplicationBasedException(
                "Scheme [{$scheme->code}] is not application-based — it does not accept scholarship applications."
            );
        }

        return $this->transaction(function () use ($data): ScholarshipApplication {
            $application = ScholarshipApplication::create([
                'school_id' => $data->schoolId,
                'academic_year_id' => $data->academicYearId,
                'scheme_id' => $data->schemeId,
                'student_id' => $data->studentId,
                'applied_by_guardian_id' => $data->appliedByGuardianId,
                'household_income_band' => $data->householdIncomeBand,
                'supporting_document_ids' => $data->supportingDocumentIds,
                'means_assessment_score' => $data->meansAssessmentScore,
                'academic_average_at_application' => $data->academicAverageAtApplication,
                'narrative' => $data->narrative,
                'status' => 'submitted',
            ]);

            event(new ApplicationSubmitted($application));

            return $application;
        });
    }
}

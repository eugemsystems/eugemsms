<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\AcademicYear;
use Modules\Finance\Domain\DataObjects\SubmitScholarshipApplicationData;
use Modules\Finance\Domain\Events\ApplicationSubmitted;
use Modules\Finance\Domain\Exceptions\SchemeNotApplicationBasedException;
use Modules\Finance\Models\DiscountScheme;
use Modules\Finance\Models\ScholarshipApplication;
use Modules\People\Models\Student;

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

        if (! $scheme->is_active) {
            throw new InvalidArgumentException('That scheme is not currently open.');
        }

        // Scheme, student and year must all be this school's own.
        if ($scheme->school_id !== $data->schoolId) {
            throw new InvalidArgumentException('That scheme belongs to another school.');
        }

        Student::query()->where('school_id', $data->schoolId)->findOrFail($data->studentId);
        AcademicYear::query()->where('school_id', $data->schoolId)->findOrFail($data->academicYearId);

        foreach (['meansAssessmentScore' => $data->meansAssessmentScore, 'academicAverageAtApplication' => $data->academicAverageAtApplication] as $label => $value) {
            if ($value !== null && (! is_numeric($value) || (float) $value < 0 || (float) $value > 100)) {
                throw new InvalidArgumentException("{$label} must be between 0 and 100.");
            }
        }

        $alreadyOpen = ScholarshipApplication::query()
            ->where('school_id', $data->schoolId)->where('scheme_id', $data->schemeId)->where('student_id', $data->studentId)
            ->where('academic_year_id', $data->academicYearId)->whereNotIn('status', ['rejected'])->exists();

        if ($alreadyOpen) {
            throw new InvalidArgumentException('This learner already has an application for that scheme and year.');
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

<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Support;

use Illuminate\Support\Collection;
use Modules\Academic\Models\AssessmentInstrument;
use Modules\Academic\Models\LearnerProject;
use Modules\Academic\Models\Subject;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\Term;
use Modules\People\Models\Student;

/**
 * Book E ACA-06 §4 ⭐. The only sanctioned route to a continuous
 * assessment figure — `ACA-05`'s `ComputeTermSubjectResultsAction`
 * calls `outcomeFor()` instead of reading `learner_projects` or
 * `legacy_cala_records` directly. Bound to
 * `EloquentContinuousAssessmentProvider` in `AcademicServiceProvider`.
 */
interface ContinuousAssessmentProvider
{
    /**
     * The continuous assessment contribution for a learner, subject
     * and academic year, or null when the subject's framework has no
     * continuous assessment model (`continuous_assessment_model = 'none'`).
     */
    public function outcomeFor(Student $student, Subject $subject, AcademicYear $year): ?ContinuousAssessmentOutcome;

    /**
     * Which instrument applies for the school's currently active
     * curriculum framework in the given year — drives report card
     * labelling.
     */
    public function activeInstrument(AcademicYear $year): ?AssessmentInstrument;

    /**
     * Learners in the given term with no *verified* outcome yet, for
     * a finalisation gate (BR-ACA-06-014).
     *
     * @return Collection<int, LearnerProject>
     */
    public function outstandingFor(Term $term): Collection;
}

<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Support;

use Illuminate\Support\Collection;
use Modules\Academic\Models\AssessmentInstrument;
use Modules\Academic\Models\CurriculumFramework;
use Modules\Academic\Models\LearnerProject;
use Modules\Academic\Models\LegacyCalaRecord;
use Modules\Academic\Models\Subject;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\Term;
use Modules\People\Models\Student;

/**
 * Book E ACA-06 §4 ⭐. Resolution order per subject: read the
 * subject's own framework's `continuous_assessment_model` —
 * `sbp` reads `learner_projects`, `cala` reads `legacy_cala_records`,
 * `none` returns null so `ACA-05` renders the subject with no
 * continuous assessment column at all.
 */
final class EloquentContinuousAssessmentProvider implements ContinuousAssessmentProvider
{
    public function outcomeFor(Student $student, Subject $subject, AcademicYear $year): ?ContinuousAssessmentOutcome
    {
        $framework = CurriculumFramework::find($subject->framework_id);

        if ($framework === null) {
            return null;
        }

        return match ($framework->continuous_assessment_model) {
            'sbp' => $this->sbpOutcome($student, $subject, $year),
            'cala' => $this->calaOutcome($student, $subject, $year),
            default => null,
        };
    }

    public function activeInstrument(AcademicYear $year): ?AssessmentInstrument
    {
        $framework = CurriculumFramework::query()
            ->where('school_id', $year->school_id)
            ->where('status', 'active')
            ->where('effective_from', '<=', $year->ends_on)
            ->orderByDesc('effective_from')
            ->first();

        if ($framework === null) {
            return null;
        }

        return AssessmentInstrument::query()
            ->where('school_id', $year->school_id)
            ->where('framework_id', $framework->id)
            ->where('status', 'active')
            ->first();
    }

    public function outstandingFor(Term $term): Collection
    {
        return LearnerProject::query()
            ->where('school_id', $term->school_id)
            ->where('academic_year_id', $term->academic_year_id)
            ->whereNull('verified_at')
            ->where('status', '!=', 'exempt')
            ->get();
    }

    private function sbpOutcome(Student $student, Subject $subject, AcademicYear $year): ?ContinuousAssessmentOutcome
    {
        $project = LearnerProject::query()
            ->where('school_id', $student->school_id)
            ->where('academic_year_id', $year->id)
            ->where('student_id', $student->id)
            ->where('subject_id', $subject->id)
            ->first();

        if ($project === null) {
            return null;
        }

        $status = match (true) {
            $project->status === 'exempt' => 'exempt',
            $project->verified_at !== null => 'verified',
            $project->marked_at !== null => 'marked',
            default => 'incomplete',
        };

        return new ContinuousAssessmentOutcome(
            instrumentCode: 'SBP',
            percent: $project->percent !== null ? (float) $project->percent : null,
            grade: $project->grade,
            status: $status,
            projectTitle: $project->project_title,
            isVerified: $project->verified_at !== null,
        );
    }

    private function calaOutcome(Student $student, Subject $subject, AcademicYear $year): ?ContinuousAssessmentOutcome
    {
        $records = LegacyCalaRecord::query()
            ->where('school_id', $student->school_id)
            ->where('academic_year_id', $year->id)
            ->where('student_id', $student->id)
            ->where('subject_id', $subject->id)
            ->get();

        if ($records->isEmpty()) {
            return null;
        }

        $percent = round((float) $records->avg('percent'), 2);

        return new ContinuousAssessmentOutcome(
            instrumentCode: 'CALA',
            percent: $percent,
            grade: null,
            status: 'verified',
            projectTitle: null,
            isVerified: true,
        );
    }
}

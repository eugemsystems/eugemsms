<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Listeners;

use Modules\Academic\Domain\Events\SubjectEnrolmentAdded;
use Modules\Academic\Models\LearnerProject;
use Modules\Academic\Models\ProjectBrief;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\School;
use Modules\People\Models\Student;
use Throwable;

/**
 * Book E ACA-06 §5/BR-ACA-06-005. A learner enrolling in a subject
 * after its brief was issued gets a project created automatically.
 * Pro-rating the deadline (the rule's own "where configured" clause)
 * is deferred — no settings key exists yet for it, so the project is
 * created with the brief's own deadline unchanged.
 *
 * Failure here never blocks the enrolment itself, mirroring
 * `CreateSubstitutionsForApprovedLeaveListener`.
 */
final class AutoCreateProjectOnLateEnrolmentListener
{
    public function handle(SubjectEnrolmentAdded $event): void
    {
        $enrolment = $event->enrolment;

        if (SchoolContext::current()?->id !== $enrolment->school_id) {
            SchoolContext::set(School::findOrFail($enrolment->school_id));
        }

        try {
            $student = Student::find($enrolment->student_id);

            if ($student === null) {
                return;
            }

            $brief = ProjectBrief::query()
                ->where('school_id', $enrolment->school_id)
                ->where('academic_year_id', $enrolment->academic_year_id)
                ->where('subject_id', $enrolment->subject_id)
                ->where('grade_level_id', $student->grade_level_id)
                ->where('status', 'issued')
                ->first();

            if ($brief === null) {
                return;
            }

            LearnerProject::firstOrCreate(
                [
                    'school_id' => $brief->school_id,
                    'brief_id' => $brief->id,
                    'student_id' => $student->id,
                ],
                [
                    'academic_year_id' => $brief->academic_year_id,
                    'subject_id' => $brief->subject_id,
                    'status' => 'assigned',
                    'version' => 1,
                ],
            );
        } catch (Throwable) {
            // A late-enrolment project problem is surfaced on the
            // subject teacher's outstanding-projects list, not by
            // failing the enrolment that already happened.
        }
    }
}

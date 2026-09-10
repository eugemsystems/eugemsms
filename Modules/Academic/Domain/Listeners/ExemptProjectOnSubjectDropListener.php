<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Listeners;

use Modules\Academic\Domain\Actions\ExemptLearnerProjectAction;
use Modules\Academic\Domain\DataObjects\ExemptLearnerProjectData;
use Modules\Academic\Domain\Events\SubjectEnrolmentDropped;
use Modules\Academic\Models\LearnerProject;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\School;
use Throwable;

/**
 * Book E ACA-06 §5/BR-ACA-06-006. A learner dropping a subject has
 * their project set to `exempt`, never deleted. Failure here never
 * blocks the drop itself, mirroring
 * `CreateSubstitutionsForApprovedLeaveListener`.
 */
final class ExemptProjectOnSubjectDropListener
{
    public function __construct(private readonly ExemptLearnerProjectAction $exemptLearnerProject) {}

    public function handle(SubjectEnrolmentDropped $event): void
    {
        $enrolment = $event->enrolment;

        if (SchoolContext::current()?->id !== $enrolment->school_id) {
            SchoolContext::set(School::findOrFail($enrolment->school_id));
        }

        try {
            $learnerProject = LearnerProject::query()
                ->where('school_id', $enrolment->school_id)
                ->where('student_id', $enrolment->student_id)
                ->where('subject_id', $enrolment->subject_id)
                ->where('academic_year_id', $enrolment->academic_year_id)
                ->whereNotIn('status', ['exempt', 'verified'])
                ->first();

            if ($learnerProject === null) {
                return;
            }

            $this->exemptLearnerProject->execute(new ExemptLearnerProjectData(
                learnerProjectId: $learnerProject->id,
                exemptionReason: "Subject dropped: {$event->change->reason}",
            ));
        } catch (Throwable) {
            // A drop-side project exemption problem is surfaced on the
            // subject teacher's outstanding-projects list, not by
            // failing the drop that already happened.
        }
    }
}

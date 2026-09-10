<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Academic\Domain\DataObjects\EnrolSubjectData;
use Modules\Academic\Domain\DataObjects\SchoolApproveSubjectSelectionData;
use Modules\Academic\Domain\Events\SubjectSelectionApproved;
use Modules\Academic\Models\SubjectSelectionSubmission;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\Term;

/**
 * ACT-SchoolApproveSubjectSelection (Book D ACA-02 §2/§6/BR-ACA-02-015).
 * Refuses a `submitted` (not yet guardian-approved) submission when
 * `academic.require_guardian_subject_approval` is on. On approval,
 * allocates immediately: every selected subject is enrolled through
 * `EnrolSubjectAction` — never a second write path into
 * `learner_subject_enrolments` — before the submission moves to
 * `allocated`. The spec names no separate persisted step between
 * `school_approved` and `allocated`, so this action performs both in
 * one transaction.
 *
 * The submission carries no `term_id` (it is dated per academic year,
 * per the spec's own schema); the school's *current* term for that
 * year is used, falling back to the year's earliest term if none is
 * marked current — the spec is silent on which term a yearly selection
 * allocates into.
 */
final class SchoolApproveSubjectSelectionAction extends Action
{
    public function __construct(
        private readonly EnrolSubjectAction $enrolSubject,
        private readonly SettingResolver $settings,
    ) {}

    public function execute(SchoolApproveSubjectSelectionData $data): SubjectSelectionSubmission
    {
        $submission = SubjectSelectionSubmission::findOrFail($data->submissionId);
        $submission->assertTransitionAllowed('school_approved');

        if ($submission->status === 'submitted') {
            $requireGuardian = (bool) $this->settings->get(
                'academic.require_guardian_subject_approval',
                new ScopeChain(schoolId: $submission->school_id),
            );

            if ($requireGuardian) {
                throw new InvalidStateTransitionException(
                    'This submission requires guardian approval before school approval (BR-ACA-02-015).',
                    ['submission_id' => $submission->id],
                );
            }
        }

        $year = AcademicYear::findOrFail($submission->academic_year_id);
        $term = $year->currentTerm() ?? Term::where('academic_year_id', $year->id)->orderBy('starts_on')->firstOrFail();

        return $this->transaction(function () use ($submission, $data, $term): SubjectSelectionSubmission {
            $submission->update([
                'status' => 'school_approved',
                'school_approved_by' => $data->approvedByUserId,
                'approved_at' => Carbon::now(),
            ]);

            foreach ($submission->selected_subject_ids as $subjectId) {
                $this->enrolSubject->execute(new EnrolSubjectData(
                    studentId: $submission->student_id,
                    subjectId: $subjectId,
                    termId: $term->id,
                    addedByUserId: $data->approvedByUserId,
                    enrolmentReason: 'elective',
                    acknowledgeWarnings: true,
                ));
            }

            $submission->update(['status' => 'allocated', 'allocated_at' => Carbon::now()]);

            event(new SubjectSelectionApproved($submission));

            return $submission->fresh();
        });
    }
}

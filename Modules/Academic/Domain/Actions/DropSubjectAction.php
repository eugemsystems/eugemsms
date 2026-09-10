<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Academic\Domain\DataObjects\DropSubjectData;
use Modules\Academic\Domain\Events\PartTimeLearnerHasNoSubjects;
use Modules\Academic\Domain\Events\SubjectEnrolmentDropped;
use Modules\Academic\Domain\Support\SubjectChangeCutoffPolicy;
use Modules\Academic\Domain\Support\TermProrationCalculator;
use Modules\Academic\Models\LearnerSubjectEnrolment;
use Modules\Academic\Models\SubjectEnrolmentChange;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\Term;
use Modules\People\Models\Student;

/**
 * ACT-DropSubject (Book D ACA-02 §4/§5, mirror of `EnrolSubjectAction`).
 * Prorates on the *unused* remainder, closes the enrolment, and emits
 * `SubjectEnrolmentDropped` for `FIN-02`'s
 * `RaiseMidTermSubjectChangeBillingListener` credit-note handler. Per
 * BR-ACA-02-011/AC-ACA-02-008, dropping to zero billable
 * subjects for a `PART_TIME` learner does not refuse the drop — it
 * fires `PartTimeLearnerHasNoSubjects` so a billing exception report
 * can surface it instead of a silent zero-value invoice.
 */
final class DropSubjectAction extends Action
{
    public function __construct(
        private readonly TermProrationCalculator $proration,
        private readonly SubjectChangeCutoffPolicy $cutoff,
    ) {}

    public function execute(DropSubjectData $data): LearnerSubjectEnrolment
    {
        $student = Student::findOrFail($data->studentId);
        $term = Term::findOrFail($data->termId);

        $enrolment = LearnerSubjectEnrolment::query()
            ->where('student_id', $student->id)
            ->where('term_id', $term->id)
            ->where('subject_id', $data->subjectId)
            ->where('status', 'active')
            ->firstOrFail();

        $this->cutoff->assertWithinCutoff($term, $student->school_id);

        $effectiveTo = $data->effectiveTo ?? Carbon::now();

        [$remaining, $total] = array_values($this->proration->remainingAndTotal($term, $effectiveTo));

        return $this->transaction(function () use ($student, $term, $enrolment, $data, $effectiveTo, $remaining, $total): LearnerSubjectEnrolment {
            $enrolment->update([
                'status' => 'dropped',
                'effective_to' => $effectiveTo->toDateString(),
                'dropped_by' => $data->droppedByUserId,
                'dropped_at' => Carbon::now(),
                'drop_reason' => $data->dropReason,
            ]);

            $change = SubjectEnrolmentChange::create([
                'school_id' => $student->school_id,
                'student_id' => $student->id,
                'term_id' => $term->id,
                'subject_id' => $enrolment->subject_id,
                'change_type' => 'dropped',
                'effective_from' => $effectiveTo->toDateString(),
                'teaching_days_remaining' => $remaining,
                'term_teaching_days' => $total,
                'proration_factor' => $total > 0 ? round($remaining / $total, 6) : 0,
                'reason' => $data->dropReason,
                'changed_by' => $data->droppedByUserId,
                'changed_at' => Carbon::now(),
            ]);

            event(new SubjectEnrolmentDropped($enrolment, $change));

            if ($student->enrolment_type === 'PART_TIME') {
                $remainingActive = LearnerSubjectEnrolment::query()
                    ->where('student_id', $student->id)
                    ->where('term_id', $term->id)
                    ->where('status', 'active')
                    ->count();

                if ($remainingActive === 0) {
                    event(new PartTimeLearnerHasNoSubjects($student));
                }
            }

            return $enrolment;
        });
    }
}
